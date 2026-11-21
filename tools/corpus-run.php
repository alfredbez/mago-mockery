<?php

declare(strict_types=1);

/**
 * Runs Mago with and without the Mockery extension against pinned OSS corpora
 * (mockery/mockery and laravel/framework test suites) and computes exact
 * diagnostic diffs, overhead ratios, and crash checks.
 *
 * Usage:
 *   php tools/corpus-run.php [--cache=/path/to/cache] [--target=name] [--json=/path/out.json] [--check]
 */

$repoRoot = dirname(__DIR__);
$options = getopt('', ['cache::', 'target::', 'json::', 'check', 'no-clone']);
$cacheDir = is_string($options['cache'] ?? null) && $options['cache'] !== ''
    ? rtrim($options['cache'], '/')
    : $repoRoot . '/.corpus-cache';
$filterTarget = is_string($options['target'] ?? null) ? $options['target'] : null;
$jsonOut = is_string($options['json'] ?? null) ? $options['json'] : null;
$checkGate = isset($options['check']);
$noClone = isset($options['no-clone']);

$magoBin = getenv('MAGO_BIN');
$magoCommand = ($magoBin !== false && $magoBin !== '')
    ? [$magoBin]
    : [PHP_BINARY, $repoRoot . '/vendor/bin/mago'];

$targets = [
    'mockery' => [
        'repo' => 'https://github.com/mockery/mockery.git',
        'ref' => '1.6.15',
        'php_version' => '8.3',
        'paths' => ['tests/Unit'],
        'includes' => ['library', 'tests/Fixture', 'vendor'],
        'max_net_regression' => 0,
    ],
    'laravel' => [
        'repo' => 'https://github.com/laravel/framework.git',
        'ref' => 'v12.73.0',
        'php_version' => '8.3',
        'paths' => ['tests/Cache', 'tests/Queue', 'tests/Events', 'tests/Mail'],
        'includes' => ['src', 'vendor'],
        'max_net_regression' => 0,
    ],
];

if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

$results = [];
$failures = 0;

foreach ($targets as $name => $spec) {
    if ($filterTarget !== null && $filterTarget !== $name) {
        continue;
    }

    $dir = $cacheDir . '/' . $name;
    if (!is_dir($dir . '/.git')) {
        if ($noClone) {
            fwrite(STDERR, "SKIP {$name}: {$dir} not cached\n");
            continue;
        }
        fwrite(STDOUT, "Cloning {$name} @ {$spec['ref']}...\n");
        $clone = runCmd(['git', 'clone', '--depth', '1', '--branch', $spec['ref'], $spec['repo'], $dir], $cacheDir);
        if ($clone['exit'] !== 0) {
            fwrite(STDERR, "FAIL {$name}: git clone failed: {$clone['stderr']}\n");
            $failures++;
            continue;
        }
    }

    if (is_file($dir . '/composer.json') && !is_dir($dir . '/vendor')) {
        $composer = is_file('/opt/mago-bin/composer.phar')
            ? [PHP_BINARY, '/opt/mago-bin/composer.phar']
            : ['composer'];
        fwrite(STDOUT, "Installing vendor deps for {$name}...\n");
        runCmd(
            array_merge($composer, ['install', '--no-interaction', '--prefer-dist', '--no-progress', '--ignore-platform-reqs', '--no-scripts']),
            $dir,
        );
    }

    $tomlPath = $dir . '/.mago-mockery-corpus.toml';
    $pathsToml = implode(', ', array_map(static fn (string $p): string => json_encode($p, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), $spec['paths']));
    $includesToml = implode(', ', array_map(static fn (string $p): string => json_encode($p, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), array_filter($spec['includes'], static fn (string $p): bool => is_dir($dir . '/' . $p))));
    $workerPath = json_encode($repoRoot . '/tests/worker.php', JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $phpBin = json_encode(PHP_BINARY, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $toml = <<<TOML
version = "1"
php-version = "{$spec['php_version']}"

[source]
paths = [{$pathsToml}]
includes = [{$includesToml}]

[analyzer]
find-unused-definitions = false
find-unused-expressions = false
check-property-initialization = false

[extension-hosts.mockery]
command = [{$phpBin}, {$workerPath}]
workers = 1
TOML;
    file_put_contents($tomlPath, $toml);

    try {
        $with = runAnalyze($magoCommand, $tomlPath, $dir, false);
        $without = runAnalyze($magoCommand, $tomlPath, $dir, true);
    } finally {
        @unlink($tomlPath);
    }

    $diff = diffIssues($without['issues'], $with['issues']);
    $overheadMs = $with['ms'] - $without['ms'];
    $overheadPct = $with['ms'] > 0 ? ($overheadMs / $with['ms']) * 100.0 : 0.0;
    $netDelta = count($with['issues']) - count($without['issues']);

    $entry = [
        'target' => $name,
        'ref' => $spec['ref'],
        'with_ms' => round($with['ms'], 1),
        'no_ext_ms' => round($without['ms'], 1),
        'overhead_ms' => round($overheadMs, 1),
        'overhead_pct' => round($overheadPct, 1),
        'with_issues' => count($with['issues']),
        'no_ext_issues' => count($without['issues']),
        'net_delta' => $netDelta,
        'eliminated_count' => count($diff['removed']),
        'added_count' => count($diff['added']),
        'crashed' => $with['crashed'],
        'stderr' => trim($with['stderr']),
        'eliminated_by_code' => countByCode($diff['removed']),
        'added_by_code' => countByCode($diff['added']),
        'added_samples' => array_slice($diff['added'], 0, 25),
    ];
    $results[$name] = $entry;

    fwrite(STDOUT, sprintf(
        "%-12s ref=%-8s with=%7.1fms no-ext=%7.1fms overhead=%+6.1fms (%4.1f%%) | no-ext=%d with=%d eliminated=%d added=%d net=%+d crashed=%s\n",
        $name,
        $spec['ref'],
        $entry['with_ms'],
        $entry['no_ext_ms'],
        $entry['overhead_ms'],
        $entry['overhead_pct'],
        $entry['no_ext_issues'],
        $entry['with_issues'],
        $entry['eliminated_count'],
        $entry['added_count'],
        $entry['net_delta'],
        $entry['crashed'] ? 'YES' : 'no',
    ));

    if ($entry['eliminated_by_code'] !== []) {
        fwrite(STDOUT, '  eliminated: ' . json_encode($entry['eliminated_by_code']) . "\n");
    }
    if ($entry['added_by_code'] !== []) {
        fwrite(STDOUT, '  added:      ' . json_encode($entry['added_by_code']) . "\n");
    }

    if ($entry['crashed']) {
        fwrite(STDERR, "FAIL {$name}: extension worker crashed: {$entry['stderr']}\n");
        $failures++;
    }
    if ($checkGate && $entry['net_delta'] > $spec['max_net_regression']) {
        fwrite(STDERR, "FAIL {$name}: net issue delta {$entry['net_delta']} exceeded threshold {$spec['max_net_regression']}\n");
        $failures++;
    }
}

if ($jsonOut !== null) {
    file_put_contents($jsonOut, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

exit($failures === 0 ? 0 : 1);

/**
 * @param list<string> $command
 * @return array{exit: int, stdout: string, stderr: string}
 */
function runCmd(array $command, string $cwd): array
{
    $p = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
    if ($p === false) {
        return ['exit' => 1, 'stdout' => '', 'stderr' => 'proc_open failed'];
    }
    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($p), 'stdout' => $stdout, 'stderr' => $stderr];
}

/**
 * @param list<string> $magoCommand
 * @return array{ms: float, crashed: bool, stderr: string, issues: list<array{code: string, file: string, line: int, message: string}>}
 */
function runAnalyze(array $magoCommand, string $tomlPath, string $cwd, bool $noExt): array
{
    $cmd = array_merge($magoCommand, ['--config', $tomlPath, '--workspace', $cwd, 'analyze', '--reporting-format=json']);
    if ($noExt) {
        $cmd[] = '--no-extensions';
    }

    $t0 = hrtime(true);
    $res = runCmd($cmd, $cwd);
    $ms = (hrtime(true) - $t0) / 1e6;

    $crashed = str_contains($res['stderr'], 'External plugin error')
        || str_contains($res['stderr'], 'worker failed')
        || str_contains($res['stderr'], 'disconnected');

    $decoded = json_decode($res['stdout'], true);
    if (!is_array($decoded) || !isset($decoded['issues']) || !is_array($decoded['issues'])) {
        return ['ms' => $ms, 'crashed' => true, 'stderr' => $res['stderr'] . "\n" . substr($res['stdout'], 0, 500), 'issues' => []];
    }

    $issues = [];
    foreach ($decoded['issues'] as $issue) {
        $primary = $issue['annotations'][0] ?? null;
        foreach ($issue['annotations'] ?? [] as $ann) {
            if (($ann['kind'] ?? '') === 'Primary') {
                $primary = $ann;
                break;
            }
        }
        $issues[] = [
            'code' => (string) ($issue['code'] ?? ''),
            'file' => (string) ($primary['span']['file_id']['name'] ?? ''),
            'line' => (int) ($primary['span']['start']['line'] ?? 0),
            'message' => (string) ($issue['message'] ?? ''),
        ];
    }

    return ['ms' => $ms, 'crashed' => $crashed, 'stderr' => $res['stderr'], 'issues' => $issues];
}

/**
 * @param list<array{code: string, file: string, line: int, message: string}> $base
 * @param list<array{code: string, file: string, line: int, message: string}> $head
 * @return array{removed: list<array{code: string, file: string, line: int, message: string}>, added: list<array{code: string, file: string, line: int, message: string}>}
 */
function diffIssues(array $base, array $head): array
{
    $keyOf = static fn (array $i): string => $i['file'] . ':' . $i['line'] . ':' . $i['code'] . ':' . $i['message'];
    $baseMap = [];
    foreach ($base as $i) {
        $k = $keyOf($i);
        $baseMap[$k] = ($baseMap[$k] ?? 0) + 1;
    }
    $headMap = [];
    foreach ($head as $i) {
        $k = $keyOf($i);
        $headMap[$k] = ($headMap[$k] ?? 0) + 1;
    }

    $removed = [];
    $seenBase = [];
    foreach ($base as $i) {
        $k = $keyOf($i);
        $seenBase[$k] = ($seenBase[$k] ?? 0) + 1;
        if ($seenBase[$k] > ($headMap[$k] ?? 0)) {
            $removed[] = $i;
        }
    }

    $added = [];
    $seenHead = [];
    foreach ($head as $i) {
        $k = $keyOf($i);
        $seenHead[$k] = ($seenHead[$k] ?? 0) + 1;
        if ($seenHead[$k] > ($baseMap[$k] ?? 0)) {
            $added[] = $i;
        }
    }

    return ['removed' => $removed, 'added' => $added];
}

/**
 * @param list<array{code: string, file: string, line: int, message: string}> $issues
 * @return array<string, int>
 */
function countByCode(array $issues): array
{
    $counts = [];
    foreach ($issues as $i) {
        $counts[$i['code']] = ($counts[$i['code']] ?? 0) + 1;
    }
    arsort($counts);

    return $counts;
}
