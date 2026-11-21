<?php

declare(strict_types=1);

require __DIR__ . '/workloads.php';

chdir(dirname(__DIR__));
$options = getopt('', ['runs:', 'iterations:', 'json:', 'compare:', 'only:', 'task:']);
$runs = filter_var($options['runs'] ?? 7, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$iterations = filter_var($options['iterations'] ?? 20000, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($runs === false || $iterations === false) {
    throw new InvalidArgumentException('--runs and --iterations must be positive integers.');
}

$workloads = MockeryBench\providerWorkloads();
$workloads = ['analyze' => static function (): void {
    $command = [PHP_BINARY, 'vendor/bin/mago', '--config', 'bench/mago.toml', 'analyze', '--reporting-format=json'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', 'php://stderr', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start Mago.');
    }
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $exit = proc_close($process);
    $report = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
    if ($exit !== 0 || !isset($report['issues']) || $report['issues'] !== []) {
        throw new RuntimeException('Benchmark analysis must have no issues: ' . $stdout);
    }
}] + $workloads;

if (isset($options['task'])) {
    $task = $options['task'];
    if (!isset($workloads[$task])) {
        throw new InvalidArgumentException('Unknown task: ' . $task);
    }
    $work = $workloads[$task];
    $count = $task === 'analyze' ? 1 : $iterations;
    for ($i = 0; $i < $count; ++$i) {
        $result = $work();
    }
    exit(0);
}

$previous = isset($options['compare'])
    ? json_decode(file_get_contents($options['compare']), true, 512, JSON_THROW_ON_ERROR)
    : null;
$fixtureFiles = glob(__DIR__ . '/fixtures/*.php');
sort($fixtureFiles);
$fixtureHash = hash('sha256', implode('', array_map('file_get_contents', $fixtureFiles)));
if ($previous !== null && ($previous['iterations'] !== $iterations || $previous['fixture_hash'] !== $fixtureHash)) {
    throw new InvalidArgumentException('Comparison requires the same fixtures and iteration count.');
}
$only = isset($options['only']) ? explode(',', $options['only']) : array_keys($workloads);
foreach ($only as $name) {
    if (!isset($workloads[$name])) {
        throw new InvalidArgumentException('Unknown workload: ' . $name);
    }
}
$report = ['php' => PHP_VERSION, 'runs' => $runs, 'iterations' => $iterations, 'fixture_hash' => $fixtureHash, 'benchmarks' => []];
printf("PHP %s, %d runs, %d provider calls per sample\n", PHP_VERSION, $runs, $iterations);
printf("%-24s %12s %12s %12s %12s\n", 'workload', 'median ms', 'min ms', 'max ms', 'change %');
foreach ($only as $name) {
    $work = $workloads[$name];
    $count = $name === 'analyze' ? 1 : $iterations;
    for ($i = 0; $i < $count; ++$i) {
        $result = $work();
    }
    $samples = [];
    for ($run = 0; $run < $runs; ++$run) {
        $start = hrtime(true);
        for ($i = 0; $i < $count; ++$i) {
            $result = $work();
        }
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    $sorted = $samples;
    sort($sorted);
    $middle = intdiv($runs, 2);
    $median = $runs % 2 === 0 ? ($sorted[$middle - 1] + $sorted[$middle]) / 2 : $sorted[$middle];
    $entry = ['median_ms' => $median, 'min_ms' => min($samples), 'max_ms' => max($samples), 'samples_ms' => $samples];
    $baseline = $previous['benchmarks'][$name]['median_ms'] ?? null;
    $entry['change_percent'] = $baseline === null ? null : ($median / $baseline - 1) * 100;
    $report['benchmarks'][$name] = $entry;
    printf("%-24s %12.3f %12.3f %12.3f %12s\n", $name, $median, $entry['min_ms'], $entry['max_ms'], $baseline === null ? '-' : sprintf('%+.1f', $entry['change_percent']));
}
if (isset($options['json'])) {
    file_put_contents($options['json'], json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
}
