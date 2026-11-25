<?php

declare(strict_types=1);

namespace MockeryFixture;

use Closure;
use Mockery;

/**
 * @param LoggableTrait|class-string<LoggableTrait> $target
 */
function overwriteTraitReceiver(object|string &$target): void
{
    $target = LoggableTrait::class;
}

final class NullsafeSink
{
    public function accept(mixed $value): void
    {
        unset($value);
    }
}

final class TraitConstantDominanceAndAliasesNegative
{
    public function conditionalMockOverPlain(bool $mockPath): void
    {
        $value = LoggableTrait::class;
        if ($mockPath) {
            $value = Mockery::mock(LoggableTrait::class);
        }
        $value::TAG;
    }

    public function conditionalPlainOverMock(bool $plainPath): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        if ($plainPath) {
            $value = LoggableTrait::class;
        }
        $value::TAG;
    }

    public function postConditionalStaleReassignment(bool $flag): void
    {
        if ($flag) {
            $value = Mockery::mock(LoggableTrait::class);
        } else {
            $value = Mockery::spy(LoggableTrait::class);
        }
        $value = LoggableTrait::class;
        $value::TAG;
    }

    public function byReferenceNonMockAlias(): void
    {
        $plain = LoggableTrait::class;
        $alias = &$plain;
        $alias::TAG;
    }

    public function byReferenceAliasInvalidatedByOriginal(): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $alias = &$mock;
        $mock = LoggableTrait::class;
        $alias::TAG;
    }

    public function originalInvalidatedByReferenceAlias(): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $alias = &$mock;
        $alias = LoggableTrait::class;
        $mock::TAG;
    }

    public function referenceCycleNonMock(): void
    {
        $a = LoggableTrait::class;
        $b = &$a;
        $a = &$b;
        $b::TAG;
    }

    public function conditionalReferenceAliasInvalidatedByOriginal(bool $flag): void
    {
        $a = Mockery::mock(LoggableTrait::class);
        $b = Mockery::mock(LoggableTrait::class);
        if ($flag) {
            $b = &$a;
        }
        $a = LoggableTrait::class;
        $b::TAG;
    }

    public function loopBackEdgeInvalidation(): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        for ($i = 0; $i < 2; ++$i) {
            $value::TAG;
            $value = LoggableTrait::class;
        }
    }

    public function byReferenceFunctionInvalidation(): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        overwriteTraitReceiver($mock);
        $mock::TAG;
    }

    public function byReferenceMethodInvalidation(): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $this->overwriteMethod($mock);
        $mock::TAG;
    }

    /**
     * @param Closure(mixed): void $fn
     */
    public function dynamicCallableInvalidation(Closure $fn): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $fn($mock);
        $mock::TAG;
    }

    public function nullsafeConditionalMockAssignment(?NullsafeSink $sink): void
    {
        $value = LoggableTrait::class;
        $sink?->accept($value = Mockery::mock(LoggableTrait::class));
        $value::TAG;
    }

    public function unparenthesizedTernaryInvalidation(bool $flag): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        $flag ? $value = LoggableTrait::class : null;
        $value::TAG;
    }

    public function matchArmConditionInvalidation(): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        match (LoggableTrait::class) {
            ($value = LoggableTrait::class) => $value::TAG,
        };
    }

    public function arrayEscapedReferenceInvalidation(): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        $box = [];
        $box[0] = &$value;
        $box[0] = LoggableTrait::class;
        $value::TAG;
    }

    public function foreachEscapedReferenceInvalidation(): void
    {
        $box = [LoggableTrait::class];
        foreach ($box as &$value) {
        }
        $value = Mockery::mock(LoggableTrait::class);
        $box[0] = LoggableTrait::class;
        $value::TAG;
    }

    public function closureCapturedReferencePersistsAcrossReassignment(): void
    {
        $value = LoggableTrait::class;
        $reset = static function () use (&$value): void {
            $value = LoggableTrait::class;
        };
        $value = Mockery::mock(LoggableTrait::class);
        $reset();
        $value::TAG;
    }

    /**
     * @param array<string, int> $xs
     */
    public function complexUnsetTargetInvalidation(array $xs): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        unset($xs[$value = LoggableTrait::class]);
        $value::TAG;
    }

    public function anonymousClassConstructorArgumentInLoop(): void
    {
        $value = Mockery::mock(LoggableTrait::class);
        for ($i = 0; $i < 2; ++$i) {
            $value::TAG;
            new class($value = LoggableTrait::class) {
                public function __construct(mixed $ignored)
                {
                    unset($ignored);
                }
            };
        }
    }

    /**
     * @param LoggableTrait|class-string<LoggableTrait> $target
     */
    private function overwriteMethod(object|string &$target): void
    {
        $target = LoggableTrait::class;
    }
}
