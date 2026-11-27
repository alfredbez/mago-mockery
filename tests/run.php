<?php

declare(strict_types=1);

chdir(dirname(__DIR__));

require __DIR__ . '/check-providers.php';

$failures = 0;

$magoBinEnv = getenv('MAGO_BIN');
$magoCommand = ($magoBinEnv !== false && $magoBinEnv !== '')
    ? [$magoBinEnv]
    : [PHP_BINARY, 'vendor/bin/mago'];

$version = trim(run([...$magoCommand, '--version'])['stdout']);
fwrite(STDOUT, $version . "\n\n");
if (preg_match('/^mago (\d+\.\d+\.\d+)/', $version, $versionMatch) !== 1 || version_compare($versionMatch[1], '1.60.2', '<')) {
    fwrite(STDERR, "The fixture expectations require Mago 1.60.2 or newer\n");
    exit(1);
}

$validated = run([...$magoCommand, 'extension', 'validate']);
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
        'tests/fixtures/TraitConstantDominanceAndAliases.php' => [],
        'tests/fixtures/TraitConstantProvenance.php' => [
            'deprecated-method' => 2,
            'mixed-assignment' => 1,
            'mixed-return-statement' => 1,
            'redundant-null-coalesce' => 3,
        ],
        'tests/fixtures/Wave9FactoriesAndTraits.php' => [],
        'tests/fixtures/Wave12Contracts.php' => [],
        'tests/fixtures/NamedMockScoping.php' => [],
        'tests/fixtures/NamedMockScopingNegative.php' => [
            'non-existent-method' => 2,
        ],
        'tests/fixtures/SignatureOverridesAndUnions.php' => [
            'deprecated-method' => 1,
        ],
        'tests/fixtures/SignatureOverridesAndUnionsNegative.php' => [
            'invalid-argument' => 4,
            'too-many-arguments' => 1,
        ],
        'tests/fixtures/IntersectionControl.php' => [
            'invalid-argument' => 1,
        ],
        'tests/fixtures/TraitConstantDominanceAndAliasesNegative.php' => [
            'direct-trait-constant-access' => 28,
            'reference-reused-from-confusing-scope' => 1,
        ],
        'tests/fixtures/TraitConstantProvenanceNegative.php' => [
            'deprecated-method' => 1,
            'direct-trait-constant-access' => 50,
            'redundant-null-coalesce' => 4,
        ],
        'tests/fixtures/Wave9FactoriesAndTraitsNegative.php' => [
            'direct-trait-constant-access' => 3,
            'non-existent-method' => 6,
        ],
        'tests/fixtures/Wave12ContractsNegative.php' => [
            'non-existent-method' => 6,
            'too-few-arguments' => 1,
        ],
        'tests/fixtures/Negative.php' => [
            // direct trait constant access on non-mock $traitClass = LoggableTrait::class
            'direct-trait-constant-access' => 1,
            // unknown methods on mocks, string specs, object partial, trait mock, fetched mock, after getMock()/fluent builders/higher-order,
            // plus Expectation method `andReturn` called on VerificationDirector
            'non-existent-method' => 11,
            // totallyFakeExpectationMethod on CompositeExpectation
            'non-documented-method' => 1,
            // Greeter mock & overload:Greeter mock passed where an ApiClient is expected + invalid expectation/matcher args
            'invalid-argument' => 15,
            // chaining after void returns: CompositeExpectation::add(), shouldNotHaveReceived('name'),
            // shouldNotHaveBeenCalled(), and higher-order shouldNotHaveReceived()->greet()
            'invalid-method-access' => 4,
            // unguarded call on nullable Mockery::fetchMock() result
            'possible-method-access-on-null' => 1,
        ],
    ],
    true => [
        // Without the extension every mock is a plain MockInterface.
        'tests/fixtures/ExpectationChains.php' => [
            // compositeExpectationChains()/verificationChains()/higherOrderMessages()/fluentMockBuilders():
            // Expectation and LegacyMockInterface are not the runtime types, so the chains
            // degrade to mixed/null/too-few-arguments/non-existent-method.
            'invalid-argument' => 4,
            'mixed-assignment' => 2,
            'mixed-method-access' => 7,
            'non-documented-method' => 27,
            'non-existent-method' => 23,
            'null-argument' => 1,
            'possible-method-access-on-null' => 1,
            'too-few-arguments' => 2,
        ],
        'tests/fixtures/MockSpecs.php' => [
            'impossible-assignment' => 1,
            'invalid-argument' => 12,
            'non-existent-class-constant' => 1,
            'non-existent-method' => 31,
            'possibly-invalid-argument' => 2,
        ],
        'tests/fixtures/Positive.php' => [
            'non-existent-method' => 7,
            'possibly-invalid-argument' => 1,
        ],
        'tests/fixtures/TraitConstantDominanceAndAliases.php' => [
            'impossible-assignment' => 1,
            'invalid-operand' => 2,
            'never-return' => 11,
            'non-existent-class-constant' => 13,
        ],
        'tests/fixtures/TraitConstantDominanceAndAliasesNegative.php' => [
            'direct-trait-constant-access' => 15,
            'non-existent-class-constant' => 13,
            'possibly-invalid-argument' => 2,
            'reference-reused-from-confusing-scope' => 1,
        ],
        'tests/fixtures/TraitConstantProvenance.php' => [
            'deprecated-method' => 2,
            'direct-trait-constant-access' => 3,
            'impossible-assignment' => version_compare($versionMatch[1], '1.61.0', '>=') ? 1 : 4,
            'mixed-assignment' => 2,
            'mixed-return-statement' => 2,
            'never-return' => version_compare($versionMatch[1], '1.61.0', '>=') ? 23 : 25,
            'non-existent-class-constant' => 26,
            'non-existent-method' => 1,
            'redundant-null-coalesce' => 3,
        ],
        'tests/fixtures/TraitConstantProvenanceNegative.php' => [
            'deprecated-method' => 1,
            'direct-trait-constant-access' => 22,
            'mixed-assignment' => 1,
            'non-existent-class-constant' => 27,
            'non-existent-method' => 1,
            'possibly-invalid-argument' => 1,
            'redundant-null-coalesce' => 4,
        ],
        'tests/fixtures/Wave12Contracts.php' => [
            'invalid-property-assignment-value' => 1,
            'mixed-argument' => 2,
            'mixed-assignment' => 4,
            'mixed-method-access' => 2,
            'mixed-operand' => 3,
            'non-documented-method' => 7,
            'non-existent-method' => 19,
            'null-argument' => 1,
            'possibly-invalid-argument' => 4,
            'redundant-condition' => 1,
            'too-many-arguments' => 1,
            'unreachable-else-clause' => 1,
        ],
        'tests/fixtures/Wave12ContractsNegative.php' => [
            'non-existent-method' => 7,
            'too-few-arguments' => 1,
        ],
        'tests/fixtures/NamedMockScoping.php' => [
            'impossible-condition' => 1,
            'invalid-argument' => 1,
            'mixed-return-statement' => 1,
            'non-existent-method' => 6,
            'possibly-invalid-argument' => 2,
        ],
        'tests/fixtures/NamedMockScopingNegative.php' => [
            'non-existent-method' => 2,
        ],
        'tests/fixtures/SignatureOverridesAndUnions.php' => [
            'deprecated-method' => 1,
            'non-documented-method' => 8,
            'non-existent-method' => 8,
            'redundant-condition' => 1,
            'unreachable-else-clause' => 1,
        ],
        'tests/fixtures/SignatureOverridesAndUnionsNegative.php' => [
            'invalid-argument' => 2,
            'non-existent-method' => 2,
            'too-many-arguments' => 1,
        ],
        'tests/fixtures/Wave9FactoriesAndTraits.php' => [
            'impossible-assignment' => 5,
            'non-documented-method' => 8,
            'non-existent-class-constant' => 5,
            'non-existent-method' => 9,
            'possibly-invalid-argument' => 2,
        ],
        'tests/fixtures/Wave9FactoriesAndTraitsNegative.php' => [
            'direct-trait-constant-access' => 3,
            'impossible-assignment' => 1,
            'non-documented-method' => 2,
            'non-existent-class-constant' => 1,
            'non-existent-method' => 7,
        ],
        // The control group must not change.
        'tests/fixtures/IntersectionControl.php' => [
            'invalid-argument' => 1,
        ],
        'tests/fixtures/Negative.php' => [
            'direct-trait-constant-access' => 1,
            'invalid-argument' => 8,
            'invalid-method-access' => 2,
            'mixed-method-access' => 1,
            'non-documented-method' => 8,
            'non-existent-method' => 18,
            'possible-method-access-on-null' => 2,
            'possibly-invalid-argument' => 1,
            'too-few-arguments' => 2,
        ],
    ],
];

foreach ([false => 'with extension', true => '--no-extensions'] as $disabled => $label) {
    $failures += assertHistograms($label, $disabled ? $without : $with, $expected[$disabled]);
}

$withStrict = analyze(false, null, ['MAGO_MOCKERY_STRICT_METHODS' => '1']);
foreach ([
    'tests/fixtures/TraitConstantDominanceAndAliases.php' => [],
    'tests/fixtures/TraitConstantDominanceAndAliasesNegative.php' => [
        'direct-trait-constant-access' => 28,
        'reference-reused-from-confusing-scope' => 1,
    ],
    'tests/fixtures/TraitConstantProvenance.php' => [
        'deprecated-method' => 2,
        'mixed-assignment' => 1,
        'mixed-return-statement' => 1,
        'redundant-null-coalesce' => 3,
    ],
    'tests/fixtures/TraitConstantProvenanceNegative.php' => [
        'deprecated-method' => 1,
        'direct-trait-constant-access' => 50,
        'redundant-null-coalesce' => 4,
    ],
] as $fixtureFile => $expectedCounts) {
    $strictSubset = array_values(array_filter(
        $withStrict,
        static fn (array $issue): bool => $issue['file'] === $fixtureFile,
    ));
    $failures += assertHistograms('with extension (strict)', $strictSubset, [$fixtureFile => $expectedCounts]);
}

foreach ([
    'tests/fixtures/TraitConstantDominanceAndAliasesNegative.php' => [34, 43, 54, 61, 69, 77, 85, 96, 103, 112, 119, 129, 136, 143, 150, 160, 170, 181, 191, 198],
    'tests/fixtures/TraitConstantProvenanceNegative.php' => [74, 96, 105, 118, 128, 136, 144, 156, 163, 172, 185, 198, 205, 217, 223, 230, 236, 243, 250, 257, 264, 273, 280, 291, 307, 319, 335, 345, 353, 369, 385, 402, 415, 429, 437, 445],
] as $negativeFile => $expectedLines) {
    foreach ($expectedLines as $expectedLine) {
        $found = 0;
        foreach ($with as $issue) {
            if (
                $issue['file'] === $negativeFile
                && $issue['code'] === 'direct-trait-constant-access'
                && $issue['line'] === $expectedLine
            ) {
                $found++;
            }
        }
        if ($found >= 1) {
            fwrite(STDOUT, "ok  with extension  {$negativeFile}:{$expectedLine}  direct-trait-constant-access = {$found}\n");
        } else {
            $failures++;
            fwrite(STDERR, "FAIL with extension  {$negativeFile}:{$expectedLine}  expected >= 1 direct-trait-constant-access, actual 0\n");
        }
    }
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
    'invalid-argument',
    'CompositeExpectation::andreturnarg',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'Expectation::andReturnArg',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'VerificationDirector::withArgs',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'Mockery::type',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'Mockery::pattern',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'Mockery::ducktype',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'invalid-argument',
    'Mockery::hasKey',
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
    'non-existent-method',
    'Method `missingOnTraitMock` does not exist on type `MockeryFixture\\LoggableTrait`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/Negative.php',
    'non-existent-method',
    'Method `missingOnFetchedMock` does not exist on type `MockeryFixture\\Greeter`',
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

$phpunitWith = analyze(false, 'tests/phpunit-integration.toml');
$phpunitWithout = analyze(true, 'tests/phpunit-integration.toml');
$failures += assertHistograms('phpunit with extension', $phpunitWith, [
    'tests/phpunit-fixtures/PhpUnitIntegration.php' => [
        // UninitializedPropertyNegativeFixture::$neverInitialized + PlainPhpUnitControlFixture::$initializedInSetUpOnly
        'uninitialized-property' => 2,
    ],
]);
$failures += assertHistograms('phpunit --no-extensions', $phpunitWithout, [
    'tests/phpunit-fixtures/PhpUnitIntegration.php' => [
        'invalid-property-assignment-value' => 3,
        'uninitialized-property' => 5,
    ],
]);
$failures += assertMessageCount(
    'phpunit with extension',
    $phpunitWith,
    'tests/phpunit-fixtures/PhpUnitIntegration.php',
    'uninitialized-property',
    'neverInitialized',
    1,
);
$failures += assertMessageCount(
    'phpunit with extension',
    $phpunitWith,
    'tests/phpunit-fixtures/PhpUnitIntegration.php',
    'uninitialized-property',
    'initializedInSetUpOnly',
    1,
);

$compat1612With = analyze(false, 'tests/mockery-1612.toml');
$compat1612Without = analyze(true, 'tests/mockery-1612.toml');
$failures += assertHistograms('mockery 1.6.12 with extension', $compat1612With, [
    'tests/mockery-1612-fixtures/Mockery1612Compat.php' => [],
    'tests/mockery-1612-fixtures/Mockery1612Negative.php' => [
        'invalid-argument' => 2,
        'non-existent-method' => 5,
        'too-many-arguments' => 1,
    ],
]);
$failures += assertHistograms('mockery 1.6.12 --no-extensions', $compat1612Without, [
    'tests/mockery-1612-fixtures/Mockery1612Compat.php' => [
        'invalid-argument' => 7,
        'mixed-method-access' => 1,
        'non-documented-method' => 6,
        'non-existent-method' => 17,
        'possibly-invalid-argument' => 5,
        'template-constraint-violation' => 8,
        'too-many-arguments' => 2,
    ],
    'tests/mockery-1612-fixtures/Mockery1612Negative.php' => [
        'invalid-argument' => 4,
        'non-existent-method' => 5,
        'too-many-arguments' => 1,
    ],
]);
foreach ([
    'missingAfterPartial',
    'missingAfterByDefault',
    'missingAfterProtected',
    'missingAfterAllowMethod',
    'missingAfterDefer',
] as $missingBuilderMethod) {
    $failures += assertMessageCount(
        'mockery 1.6.12 with extension',
        $compat1612With,
        'tests/mockery-1612-fixtures/Mockery1612Negative.php',
        'non-existent-method',
        "Method `{$missingBuilderMethod}` does not exist on type `MockeryCompat1612\\CompatGreeter`",
        1,
    );
}

$strictDefault = analyze(false, 'tests/strict-methods.toml');
$strictEnabled = analyze(false, 'tests/strict-methods.toml', ['MAGO_MOCKERY_STRICT_METHODS' => '1']);
$failures += assertHistograms('strict default mode', $strictDefault, [
    'tests/strict-fixtures/StrictMethods.php' => [],
    'tests/strict-fixtures/Wave9StrictGaps.php' => [],
    'tests/strict-fixtures/Wave12Strict.php' => [],
    'tests/strict-fixtures/StrictAllowancesAndUnions.php' => [],
]);
$failures += assertHistograms('strict enabled mode', $strictEnabled, [
    'tests/strict-fixtures/StrictMethods.php' => [
        'alfredbez/mockery/unknown-mocked-method' => 13,
    ],
    'tests/strict-fixtures/Wave9StrictGaps.php' => [
        'alfredbez/mockery/unknown-mocked-method' => 7,
    ],
    'tests/strict-fixtures/Wave12Strict.php' => [
        'alfredbez/mockery/unknown-mocked-method' => 5,
    ],
    'tests/strict-fixtures/StrictAllowancesAndUnions.php' => [
        'alfredbez/mockery/unknown-mocked-method' => 8,
    ],
]);
foreach ([
    'dynamicAfterReassignment' => 'MockeryFixture\\Strict\\StrictGreeter',
    'dynamicAfterCopyFromOther' => 'MockeryFixture\\Strict\\StrictGreeter',
    'dynamicAfterRefReassign' => 'MockeryFixture\\Strict\\StrictGreeter',
    'dynamicAfterConditional' => 'MockeryFixture\\Strict\\StrictGreeter',
    'dynamicAfterLoop' => 'MockeryFixture\\Strict\\StrictGreeter',
    'missingHigherOrder' => 'MockeryFixture\\Strict\\StrictGreeter',
    'missingHigherOrderOnSub' => 'MockeryFixture\\Strict\\SubStrictGreeter',
] as $missingMethod => $targetType) {
    $failures += assertMessageCount(
        'strict enabled mode',
        $strictEnabled,
        'tests/strict-fixtures/Wave9StrictGaps.php',
        'alfredbez/mockery/unknown-mocked-method',
        "Method `{$missingMethod}` does not exist on mocked type `{$targetType}`",
        1,
    );
}
foreach (['missing', 'dynAfterReassign', 'dynConditional', 'dynAfterUnset', 'dynAfterPropRefReassign'] as $missingMethod) {
    $failures += assertMessageCount(
        'strict enabled mode',
        $strictEnabled,
        'tests/strict-fixtures/Wave12Strict.php',
        'alfredbez/mockery/unknown-mocked-method',
        "Method `{$missingMethod}` does not exist on mocked type `MockeryFixture\\Strict\\StrictGreeter`",
        1,
    );
}
foreach ([
    'dynAfterHelper',
    'dynAfterThisAlias',
    'dynOnChild',
    'dynAfterByRef',
    'dynAfterUnknownCall',
    'missingUnionA',
    'missingUnionB',
    'missingUnionPartial',
] as $missingMethod) {
    $failures += assertMessageCount(
        'strict enabled mode',
        $strictEnabled,
        'tests/strict-fixtures/StrictAllowancesAndUnions.php',
        'alfredbez/mockery/unknown-mocked-method',
        "Method `{$missingMethod}` does not exist on mocked type `MockeryFixture\\Strict\\StrictGreeter`",
        1,
    );
}
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/NamedMockScopingNegative.php',
    'non-existent-method',
    'Method `fetch` does not exist on type `MockeryFixture\\Greeter`',
    1,
);
$failures += assertMessageCount(
    'with extension',
    $with,
    'tests/fixtures/NamedMockScopingNegative.php',
    'non-existent-method',
    'Method `greet` does not exist on type `Mockery\\MockInterface`',
    1,
);
$failures += assertMessageCount(
    'strict enabled mode',
    $strictEnabled,
    'tests/strict-fixtures/Wave12Strict.php',
    'non-documented-method',
    'missing',
    0,
);
foreach ([
    'Method `missingOnClassString` does not exist on type `MockeryFixture\\Greeter`',
    'Method `missingOnNamedFetch` does not exist on type `MockeryFixture\\Greeter`',
] as $needle) {
    $failures += assertMessageCount(
        'with extension',
        $with,
        'tests/fixtures/Wave9FactoriesAndTraitsNegative.php',
        'non-existent-method',
        $needle,
        1,
    );
}
foreach ([
    'doesNotExist',
    'missingChained',
    'missingNotReceive',
    'missingAllows',
    'missingMapKey',
    'missingExpects',
    'missingHaveReceived',
    'missingNotHaveReceived',
    'missingDemeter',
    'stillMissingAfterAllow',
    'allowedSeparate',
    'onlyOnOtherMock',
    'commentedOut',
] as $missingMethod) {
    $failures += assertMessageCount(
        'strict enabled mode',
        $strictEnabled,
        'tests/strict-fixtures/StrictMethods.php',
        'alfredbez/mockery/unknown-mocked-method',
        "Method `{$missingMethod}` does not exist on mocked type `MockeryFixture\\Strict\\StrictGreeter`",
        1,
    );
}

if ($failures > 0) {
    fwrite(STDERR, "\n{$failures} assertion(s) failed\n");
    exit(1);
}

fwrite(STDOUT, "PASS\n");
exit(0);

/**
 * @param list<string> $command
 * @param array<string, string>|null $env
 * @return array{exit: int, stdout: string, stderr: string}
 */
function run(array $command, ?array $env = null): array
{
    $environment = $env === null ? null : array_merge($_ENV, getenv(), $env);
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
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

/**
 * @param array<string, string>|null $env
 * @return list<array{level: string, code: string, file: string, line: int, message: string}>
 */
function analyze(bool $noExtensions, ?string $config = null, ?array $env = null): array
{
    $magoBinEnv = getenv('MAGO_BIN');
    $command = ($magoBinEnv !== false && $magoBinEnv !== '')
        ? [$magoBinEnv]
        : [PHP_BINARY, 'vendor/bin/mago'];
    if ($config !== null) {
        $command[] = '--config';
        $command[] = $config;
    }
    $command[] = 'analyze';
    $command[] = '--reporting-format=json';
    if ($noExtensions) {
        $command[] = '--no-extensions';
    }

    $result = run($command, $env);
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

        $start = $primary['span']['start'];
        $line = array_key_exists('column', $start)
            ? (int) $start['line']
            : (int) $start['line'] + 1;

        $issues[] = [
            'level' => (string) $issue['level'],
            'code' => (string) $issue['code'],
            'file' => (string) $primary['span']['file_id']['name'],
            'line' => $line,
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
