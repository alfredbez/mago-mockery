<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

$failures = 0;

$version = trim(run([PHP_BINARY, 'vendor/bin/mago', '--version'])['stdout']);
fwrite(STDOUT, $version . "\n\n");

$validated = run([PHP_BINARY, 'vendor/bin/mago', 'extension', 'validate']);
fwrite(STDOUT, "extension validate (exit {$validated['exit']}):\n");
fwrite(STDOUT, trim($validated['stdout'] . $validated['stderr']) . "\n\n");
if ($validated['exit'] !== 0) {
    fwrite(STDERR, "extension validate failed\n");
    exit(1);
}

$with = analyze(false);
$without = analyze(true);

fwrite(STDOUT, "with extension (" . count($with) . " issues):\n");
printIssues($with);
fwrite(STDOUT, "\n--no-extensions (" . count($without) . " issues):\n");
printIssues($without);
fwrite(STDOUT, "\n");

// The extension must remove the Mockery false positives and keep the real ones.
$expected = [
    false => [
        'tests/fixtures/ExpectationChains.php' => [],
        'tests/fixtures/MockSpecs.php' => [],
        'tests/fixtures/Positive.php' => [],
        'tests/fixtures/IntersectionControl.php' => [
            'invalid-argument' => 1,
        ],
        'tests/fixtures/Negative.php' => [
            // unknown methods on mocks, string specs, object partial, after getMock()/fluent builders/higher-order,
            // plus Expectation method `andReturn` called on VerificationDirector
            'non-existent-method' => 9,
            // totallyFakeExpectationMethod on CompositeExpectation
            'non-documented-method' => 1,
            // Greeter mock & overload:Greeter mock passed where an ApiClient is expected + times('not-an-int')
            'invalid-argument' => 3,
            // chaining after void returns: CompositeExpectation::add(), shouldNotHaveReceived('name'),
            // shouldNotHaveBeenCalled(), and higher-order shouldNotHaveReceived()->greet()
            'invalid-method-access' => 4,
        ],
    ],
    true => [
        // Without the extension every mock is a plain MockInterface.
        'tests/fixtures/ExpectationChains.php' => [
            // compositeExpectationChains()/verificationChains()/higherOrderMessages()/fluentMockBuilders():
            // Expectation and LegacyMockInterface are not the runtime types, so the chains
            // degrade to mixed/null/too-few-arguments/non-existent-method.
            'mixed-assignment' => 2,
            'mixed-method-access' => 7,
            'non-documented-method' => 27,
            'non-existent-method' => 22,
            'null-argument' => 1,
            'possible-method-access-on-null' => 1,
            'too-few-arguments' => 2,
        ],
        'tests/fixtures/MockSpecs.php' => [
            'invalid-argument' => 7,
            'non-existent-method' => 14,
            'possibly-invalid-argument' => 1,
        ],
        'tests/fixtures/Positive.php' => [
            'non-existent-method' => 7,
            'possibly-invalid-argument' => 1,
        ],
        // The control group must not change.
        'tests/fixtures/IntersectionControl.php' => [
            'invalid-argument' => 1,
        ],
        'tests/fixtures/Negative.php' => [
            'invalid-argument' => 3,
            'invalid-method-access' => 2,
            'mixed-method-access' => 1,
            'non-documented-method' => 8,
            'non-existent-method' => 15,
            'possible-method-access-on-null' => 1,
            'possibly-invalid-argument' => 1,
            'too-few-arguments' => 2,
        ],
    ],
];

foreach ([false => 'with extension', true => '--no-extensions'] as $disabled => $label) {
    $failures += assertHistograms($label, $disabled ? $without : $with, $expected[$disabled]);
}

$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'missingMethod',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-documented-method',
    'totallyFakeExpectationMethod',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'CompositeExpectation::times',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-method-access',
    'andReturn()',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'missingAfterGetMock',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'found `MockeryFixture\\Greeter&Mockery\\MockInterface`',
    2,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'Method `missingOnPartialSpec` does not exist on type `MockeryFixture\\ApiClient`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'Method `missingOnObjectPartial` does not exist on type `MockeryFixture\\GreeterImpl`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'Method `missingOnUnknownOverload` does not exist on type `Mockery\\MockInterface`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-method-access',
    'withAnyArgs()',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-method-access',
    'once()',
    2,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'Method `andReturn` does not exist on type `Mockery\\VerificationDirector`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'missingAfterPartial',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'missingAfterAllowsMap',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'missingAfterHigherOrder',
    1,
);

// KNOWN GAP (Mago 1.60.2): an argument check whose parameter type is itself an
// intersection accepts a mismatching primary class. Negative.php keeps one
// such call (apiClientMockIntoGreeter); it must stay silent until Mago compares
// intersections member by member. The histogram above records the current
// count, so a fix shows up as a new invalid-argument issue there.
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'acceptGreeter',
    0,
);

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "PASS\n");
exit(0);

/**
 * @param list<string> $command
 * @return array{exit: int, stdout: string, stderr: string}
 */
function run(array $command): array
{
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if ($process === false) {
        fwrite(STDERR, 'failed to start ' . implode(' ', $command) . "\n");
        exit(1);
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [
        'exit' => proc_close($process),
        'stdout' => $stdout === false ? '' : $stdout,
        'stderr' => $stderr === false ? '' : $stderr,
    ];
}

/** @return list<array{level: string, code: string, file: string, line: int, message: string}> */
function analyze(bool $noExtensions): array
{
    $command = [PHP_BINARY, 'vendor/bin/mago', 'analyze', '--reporting-format=json'];
    if ($noExtensions) {
        $command[] = '--no-extensions';
    }

    $result = run($command);
    if (trim($result['stderr']) !== '') {
        fwrite(STDERR, ($noExtensions ? '--no-extensions' : 'with extension') . " stderr:\n" . $result['stderr'] . "\n");
    }

    try {
        $decoded = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
    } catch (Throwable $exception) {
        fwrite(STDERR, "mago analyze did not return JSON (exit {$result['exit']}): {$exception->getMessage()}\n");
        fwrite(STDERR, $result['stdout'] . "\n");
        exit(1);
    }

    if (!is_array($decoded) || !isset($decoded['issues']) || !is_array($decoded['issues'])) {
        fwrite(STDERR, "mago analyze JSON has no issues array\n");
        exit(1);
    }

    $issues = [];
    foreach ($decoded['issues'] as $issue) {
        $primary = $issue['annotations'][0];
        foreach ($issue['annotations'] as $annotation) {
            if (($annotation['kind'] ?? '') === 'Primary') {
                $primary = $annotation;
                break;
            }
        }

        $issues[] = [
            'level' => (string) $issue['level'],
            'code' => (string) $issue['code'],
            'file' => (string) $primary['span']['file_id']['name'],
            'line' => (int) $primary['span']['start']['line'],
            'message' => (string) $issue['message'],
        ];
    }

    return $issues;
}

/** @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues */
function printIssues(array $issues): void
{
    if ($issues === []) {
        fwrite(STDOUT, "  (none)\n");

        return;
    }

    foreach ($issues as $issue) {
        fwrite(STDOUT, sprintf(
            "  %s\t%s\t%s:%d\t%s\n",
            $issue['level'],
            $issue['code'],
            $issue['file'],
            $issue['line'],
            $issue['message'],
        ));
    }
}

/**
 * @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues
 * @param array<string, array<string, int>> $expected
 */
function assertHistograms(string $label, array $issues, array $expected): int
{
    $actual = [];
    foreach ($issues as $issue) {
        $actual[$issue['file']][$issue['code']] = ($actual[$issue['file']][$issue['code']] ?? 0) + 1;
    }

    $failures = 0;
    $files = array_unique([...array_keys($expected), ...array_keys($actual)]);
    sort($files);
    foreach ($files as $file) {
        $expectedCounts = array_filter(
            $expected[$file] ?? [],
            static fn (int $count): bool => $count !== 0,
        );
        $actualCounts = $actual[$file] ?? [];
        ksort($expectedCounts);
        ksort($actualCounts);
        if ($expectedCounts === $actualCounts) {
            fwrite(STDOUT, "ok  {$label}  {$file}  " . formatCounts($actualCounts) . "\n");
            continue;
        }

        $failures++;
        fwrite(STDERR, "FAIL {$label}  {$file}\n");
        fwrite(STDERR, '  expected ' . formatCounts($expectedCounts) . "\n");
        fwrite(STDERR, '  actual   ' . formatCounts($actualCounts) . "\n");
    }

    return $failures;
}

/**
 * @param list<array{level: string, code: string, file: string, line: int, message: string}> $issues
 */
function assertMessageCount(string $label, array $issues, string $file, string $code, string $needle, int $expected): int
{
    $actual = 0;
    foreach ($issues as $issue) {
        if ($issue['file'] === $file && $issue['code'] === $code && str_contains($issue['message'], $needle)) {
            $actual++;
        }
    }

    if ($actual === $expected) {
        fwrite(STDOUT, "ok  {$label}  {$file}  {$code} containing \"{$needle}\" = {$actual}\n");

        return 0;
    }

    fwrite(STDERR, "FAIL {$label}  {$file}  {$code} containing \"{$needle}\"\n");
    fwrite(STDERR, "  expected {$expected}, actual {$actual}\n");

    return 1;
}

/** @param array<string, int> $counts */
function formatCounts(array $counts): string
{
    if ($counts === []) {
        return '(none)';
    }

    $parts = [];
    foreach ($counts as $code => $count) {
        $parts[] = "{$code}={$count}";
    }

    return implode(', ', $parts);
}
