<?php

declare(strict_types=1);

namespace MockeryFixture;

use Closure;
use Mockery;
use Stringable;

use function implode;

function maybeReenterTraitHelper(): void {}

/** @pure */
function fakePureCaller(): void {}

final class EvilCloner
{
    /** @var ?Closure(): void */
    private ?Closure $onClone;

    /** @param ?Closure(): void $onClone */
    public function __construct(?Closure $onClone = null)
    {
        $this->onClone = $onClone;
    }

    public function __clone(): void
    {
        if ($this->onClone !== null) {
            ($this->onClone)();
        }
    }
}

final class EvilStringable implements Stringable
{
    /** @param Closure(): void $onToString */
    public function __construct(private Closure $onToString) {}

    public function __toString(): string
    {
        ($this->onToString)();

        return '';
    }
}

class CtorInvoker
{
    /** @param Closure(): void $callback */
    public function __construct(Closure $callback)
    {
        $callback();
    }
}

class OverridablePureBase
{
    /** @pure */
    protected function poke(): void {}

    public function staticLocalOverridablePureMethod(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $mock = Mockery::mock(LoggableTrait::class);
        $this->poke();
        $mock::TAG;
    }
}

trait NonFluentTrait
{
    public const TAG = 'non-fluent';

    /** @return class-string<NonFluentTrait> */
    public function className(): string
    {
        return NonFluentTrait::class;
    }
}

final class TraitConstantProvenanceNegative
{
    public function staticLocalDirectReassignment(): void
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        $mock = LoggableTrait::class;
        $mock::TAG;
    }

    public function staticLocalConditionalAssignment(bool $flag): void
    {
        static $mock = LoggableTrait::class;
        if ($flag) {
            $mock = Mockery::mock(LoggableTrait::class);
        }
        $mock::TAG;
    }

    public function staticLocalRecursiveCallInvalidation(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $mock = Mockery::mock(LoggableTrait::class);
        $this->staticLocalRecursiveCallInvalidation(true);
        $mock::TAG;
    }

    public function staticLocalEscapedAliasInvalidation(): void
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        $box = [];
        $box[0] = &$mock;
        $box[0] = LoggableTrait::class;
        $mock::TAG;
    }

    public function staticLocalByRefCallInvalidation(): void
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        overwriteTraitReceiver($mock);
        $mock::TAG;
    }

    public function staticLocalUnknownUserCallInvalidation(): void
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        maybeReenterTraitHelper();
        $mock::TAG;
    }

    public function staticLocalCoalesceAssignWithoutReset(bool $poison): void
    {
        static $mock = null;
        if ($poison) {
            $mock = LoggableTrait::class;

            return;
        }
        $mock ??= Mockery::mock(LoggableTrait::class);
        $mock::TAG;
    }

    public function doWhileUnknownConditionSecondIteration(bool $cond): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $mock::TAG;
            $mock = LoggableTrait::class;
        } while ($cond);
    }

    public function doWhileTrueSecondIteration(bool $stop): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $mock::TAG;
            $mock = LoggableTrait::class;
            if ($stop) {
                break;
            }
        } while (true);
    }

    public function doWhileFalseAfterReassignment(): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $mock = LoggableTrait::class;
            $mock::TAG;
        } while (false);
    }

    public function doWhileFalsePostLoopEarlyBreak(bool $flag): void
    {
        $mock = LoggableTrait::class;
        do {
            if ($flag) {
                break;
            }
            $mock = Mockery::mock(LoggableTrait::class);
        } while (false);
        $mock::TAG;
    }

    public function doWhileTruthyStringCondition(bool $stop): void
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $mock::TAG;
            $mock = LoggableTrait::class;
            if ($stop) {
                break;
            }
        } while ('false');
    }

    public function shouldDeferMissingThenReassigned(): void
    {
        $mock = Mockery::mock(LoggableTrait::class)->shouldDeferMissing();
        $mock = LoggableTrait::class;
        $mock::TAG;
    }

    public function nonMockReturningFluentChain(): void
    {
        $class = Mockery::mock(NonFluentTrait::class)->className();
        $class::TAG;
    }

    public function cloneReassignedToClassString(): void
    {
        $clone = clone Mockery::mock(LoggableTrait::class);
        $clone = LoggableTrait::class;
        $clone::TAG;
    }

    public function cloneConditionalNonMock(bool $flag): void
    {
        $clone = $flag ? clone Mockery::mock(LoggableTrait::class) : LoggableTrait::class;
        $clone::TAG;
    }

    public function coalesceAssignOnClassString(): void
    {
        $value = LoggableTrait::class;
        $value ??= Mockery::mock(LoggableTrait::class);
        $value::TAG;
    }

    public function coalesceAssignNullableMockWithClassStringFallback(bool $flag): void
    {
        $value = $flag ? Mockery::mock(LoggableTrait::class) : null;
        $value ??= LoggableTrait::class;
        $value::TAG;
    }

    public function coalesceAssignConditionalClassStringOrNull(bool $flag): void
    {
        $value = $flag ? LoggableTrait::class : null;
        $value ??= Mockery::mock(LoggableTrait::class);
        $value::TAG;
    }

    public function coalesceAssignOnKnownNullToClassString(): void
    {
        $value = null;
        $value ??= LoggableTrait::class;
        $value::TAG;
    }

    /**
     * @param LoggableTrait|class-string<LoggableTrait>|null $value
     */
    public function coalesceAssignOnParameter(object|string|null $value): void
    {
        $value ??= Mockery::mock(LoggableTrait::class);
        $value::TAG;
    }

    public function coalesceExprOnClassString(): void
    {
        $value = LoggableTrait::class;
        $resolved = $value ?? Mockery::mock(LoggableTrait::class);
        $resolved::TAG;
    }

    public function cloneMutatesEscapedRef(): void
    {
        $mock = LoggableTrait::class;
        $evil = new EvilCloner(static function () use (&$mock): void {
            $mock = LoggableTrait::class;
        });
        $mock = Mockery::mock(LoggableTrait::class);
        $_ = clone $evil;
        $mock::TAG;
    }

    public function cloneMutatesStaticLocal(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $evil = new EvilCloner(function (): void {
            $this->cloneMutatesStaticLocal(true);
        });
        $mock = Mockery::mock(LoggableTrait::class);
        $_ = clone $evil;
        $mock::TAG;
    }

    public function cloneMutatesEscapedNullBeforeCoalesceAssign(): void
    {
        $value = null;
        $evil = new EvilCloner(static function () use (&$value): void {
            $value = LoggableTrait::class;
        });
        $value = null;
        $_ = clone $evil;
        $value ??= Mockery::mock(LoggableTrait::class);
        $value::TAG;
    }

    public function cloneInLoopMutatesStaticLocal(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $evil = new EvilCloner(function (): void {
            $this->cloneInLoopMutatesStaticLocal(true);
        });
        $mock = Mockery::mock(LoggableTrait::class);
        for ($i = 0; $i < 2; ++$i) {
            $mock::TAG;
            $_ = clone $evil;
        }
    }

    public function staticLocalInitializerSideEffectOnlyRunsFirstCall(): void
    {
        $other = LoggableTrait::class;
        static $once = ($other = Mockery::mock(LoggableTrait::class));
        unset($once);
        $other::TAG;
    }

    public function staticLocalUserPureFunctionInvalidation(): void
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        fakePureCaller();
        $mock::TAG;
    }

    public function staticLocalBuiltinPureWithStringableArray(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $evil = new EvilStringable(function (): void {
            $this->staticLocalBuiltinPureWithStringableArray(true);
        });
        $mock = Mockery::mock(LoggableTrait::class);
        implode('', [$evil]);
        $mock::TAG;
    }

    public function staticLocalMockeryFactoryConstructorReentry(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $cb = function (): void {
            $this->staticLocalMockeryFactoryConstructorReentry(true);
        };
        $mock = Mockery::mock(LoggableTrait::class);
        Mockery::mock(CtorInvoker::class, [$cb]);
        $mock::TAG;
    }

    public function staticLocalContainerFactoryConstructorReentry(bool $reset): void
    {
        static $mock;
        if ($reset) {
            $mock = LoggableTrait::class;

            return;
        }
        $cb = function (): void {
            $this->staticLocalContainerFactoryConstructorReentry(true);
        };
        $container = Mockery::getContainer();
        $mock = Mockery::mock(LoggableTrait::class);
        $container->mock(CtorInvoker::class, [$cb]);
        $mock::TAG;
    }
}
