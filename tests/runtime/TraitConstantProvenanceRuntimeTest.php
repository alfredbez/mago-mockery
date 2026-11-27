<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Closure;
use Error;
use Mockery;
use PHPUnit\Framework\TestCase;
use Stringable;

use function implode;
use function strlen;

/** @pure */
function runtimeFakePureCaller(Closure $fn): void
{
    $fn();
}

final class RuntimeEvilCloner
{
    /** @param Closure(): void $onClone */
    public function __construct(private Closure $onClone) {}

    public function __clone(): void
    {
        ($this->onClone)();
    }
}

final class RuntimeEvilStringable implements Stringable
{
    /** @param Closure(): void $onToString */
    public function __construct(private Closure $onToString) {}

    public function __toString(): string
    {
        ($this->onToString)();

        return '';
    }
}

class RuntimeCtorInvoker
{
    /** @param Closure(): void $callback */
    public function __construct(Closure $callback)
    {
        $callback();
    }
}

class RuntimePureBase
{
    /** @pure */
    protected function poke(): void {}

    public function runWithStaticLocal(bool $reset): string
    {
        static $mock;
        if ($reset) {
            $mock = ProvenanceRuntimeTrait::class;

            return 'reset';
        }
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        $this->poke();

        return $mock::TAG;
    }
}

final class RuntimePureSub extends RuntimePureBase
{
    protected function poke(): void
    {
        $this->runWithStaticLocal(true);
    }
}

trait ProvenanceRuntimeTrait
{
    public const TAG = 'tag';

    /** @return class-string<ProvenanceRuntimeTrait> */
    public function className(): string
    {
        return ProvenanceRuntimeTrait::class;
    }
}

final class TraitConstantProvenanceRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testStaticLocalMockSurvivesUnrelatedPureCall(): void
    {
        static $mock;
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        strlen('unrelated');

        self::assertSame('tag', $mock::TAG);
    }

    public function testStaticLocalReentryCanOverwriteMockAndThrow(): void
    {
        $run = null;
        $run = static function (bool $reset) use (&$run): string {
            static $mock;
            if ($reset) {
                $mock = ProvenanceRuntimeTrait::class;

                return 'reset';
            }
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            $run(true);

            return $mock::TAG;
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testDoWhileFalseFirstIterationUsesPreLoopMock(): void
    {
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        do {
            $tag = $mock::TAG;
            $mock = ProvenanceRuntimeTrait::class;
        } while (false);

        self::assertSame('tag', $tag);

        $zeroMock = Mockery::mock(ProvenanceRuntimeTrait::class);
        do {
            $zeroTag = $zeroMock::TAG;
            $zeroMock = ProvenanceRuntimeTrait::class;
        } while (0);

        self::assertSame('tag', $zeroTag);
    }

    public function testDoWhileFalsePostLoopAssignmentAndEarlyBreakGuard(): void
    {
        $post = ProvenanceRuntimeTrait::class;
        do {
            $post = Mockery::mock(ProvenanceRuntimeTrait::class);
        } while (false);
        self::assertSame('tag', $post::TAG);

        $guard = static function (bool $flag): string {
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            $tag = 'skipped';
            do {
                if ($flag) {
                    $mock = ProvenanceRuntimeTrait::class;
                    break;
                }
                $tag = $mock::TAG;
                $mock = ProvenanceRuntimeTrait::class;
            } while (false);

            return $tag;
        };

        self::assertSame('skipped', $guard(true));
        self::assertSame('tag', $guard(false));
    }

    public function testDoWhileSecondIterationThrowsAfterBodyReassignment(): void
    {
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        $iterations = 0;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        do {
            $_unused = $mock::TAG;
            $mock = ProvenanceRuntimeTrait::class;
            ++$iterations;
        } while ($iterations < 2);
    }

    public function testDoWhileFalseAfterReassignmentThrows(): void
    {
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        do {
            $mock = ProvenanceRuntimeTrait::class;
            $_unused = $mock::TAG;
        } while (false);
    }

    public function testDoWhileFalsePostLoopEarlyBreakThrows(): void
    {
        $mock = ProvenanceRuntimeTrait::class;
        do {
            break;
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        } while (false);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $mock::TAG;
    }

    public function testShouldDeferMissingAndAsUndefinedReturnMock(): void
    {
        $deferred = Mockery::mock(ProvenanceRuntimeTrait::class)->shouldDeferMissing();
        self::assertSame('tag', $deferred::TAG);

        $undefined = Mockery::mock(ProvenanceRuntimeTrait::class)->asUndefined();
        self::assertSame('tag', $undefined::TAG);

        $partial = Mockery::mock(ProvenanceRuntimeTrait::class)
            ->makePartial()
            ->shouldIgnoreMissing()
            ->byDefault()
            ->shouldAllowMockingProtectedMethods();
        self::assertSame('tag', $partial::TAG);
    }

    public function testNonMockReturningMethodChainThrows(): void
    {
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        $mock->shouldReceive('className')->andReturn(ProvenanceRuntimeTrait::class);
        $class = $mock->className();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $class::TAG;
    }

    public function testClonedMockReturnsTraitConstant(): void
    {
        $direct = clone Mockery::mock(ProvenanceRuntimeTrait::class);
        self::assertSame('tag', $direct::TAG);

        $original = Mockery::mock(ProvenanceRuntimeTrait::class);
        $indirect = clone $original;
        $original = ProvenanceRuntimeTrait::class;
        self::assertSame('tag', $indirect::TAG);

        self::assertSame('tag', (clone Mockery::mock(ProvenanceRuntimeTrait::class))::TAG);
    }

    public function testConditionalCloneNonMockBranchThrows(): void
    {
        $run = static function (bool $flag): string {
            $value = $flag
                ? clone Mockery::mock(ProvenanceRuntimeTrait::class)
                : ProvenanceRuntimeTrait::class;

            return $value::TAG;
        };

        self::assertSame('tag', $run(true));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testCoalesceAssignRefinementsSucceedOnValidPaths(): void
    {
        $fromNull = null;
        $fromNull ??= Mockery::mock(ProvenanceRuntimeTrait::class);
        self::assertSame('tag', $fromNull::TAG);

        $fromMock = Mockery::mock(ProvenanceRuntimeTrait::class);
        $fromMock ??= ProvenanceRuntimeTrait::class;
        self::assertSame('tag', $fromMock::TAG);

        foreach ([true, false] as $flag) {
            $nullable = $flag ? Mockery::mock(ProvenanceRuntimeTrait::class) : null;
            $nullable ??= Mockery::mock(ProvenanceRuntimeTrait::class);
            self::assertSame('tag', $nullable::TAG);

            $repeated = null;
            if ($flag) {
                $repeated ??= Mockery::mock(ProvenanceRuntimeTrait::class);
            }
            $repeated ??= Mockery::mock(ProvenanceRuntimeTrait::class);
            self::assertSame('tag', $repeated::TAG);

            $exprBase = $flag ? Mockery::mock(ProvenanceRuntimeTrait::class) : null;
            $resolved = $exprBase ?? Mockery::mock(ProvenanceRuntimeTrait::class);
            self::assertSame('tag', $resolved::TAG);
        }

        $afterUnset = ProvenanceRuntimeTrait::class;
        unset($afterUnset);
        $afterUnset ??= Mockery::mock(ProvenanceRuntimeTrait::class);
        self::assertSame('tag', $afterUnset::TAG);
    }

    public function testCoalesceAssignOnClassStringThrows(): void
    {
        $value = ProvenanceRuntimeTrait::class;
        $value ??= Mockery::mock(ProvenanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testCoalesceAssignNullableMockWithClassStringFallbackThrowsOnNullPath(): void
    {
        $run = static function (bool $flag): string {
            $value = $flag ? Mockery::mock(ProvenanceRuntimeTrait::class) : null;
            $value ??= ProvenanceRuntimeTrait::class;

            return $value::TAG;
        };

        self::assertSame('tag', $run(true));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testCoalesceAssignConditionalClassStringOrNullThrowsOnClassStringPath(): void
    {
        $run = static function (bool $flag): string {
            $value = $flag ? ProvenanceRuntimeTrait::class : null;
            $value ??= Mockery::mock(ProvenanceRuntimeTrait::class);

            return $value::TAG;
        };

        self::assertSame('tag', $run(false));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(true);
    }

    public function testCloneMutatingEscapedRefThrows(): void
    {
        $mock = ProvenanceRuntimeTrait::class;
        $evil = new RuntimeEvilCloner(static function () use (&$mock): void {
            $mock = ProvenanceRuntimeTrait::class;
        });
        $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
        $_ = clone $evil;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $mock::TAG;
    }

    public function testCloneMutatingStaticLocalThrows(): void
    {
        $run = null;
        $run = static function (bool $reset) use (&$run): string {
            static $mock;
            if ($reset) {
                $mock = ProvenanceRuntimeTrait::class;

                return 'reset';
            }
            $evil = new RuntimeEvilCloner(static function () use (&$run): void {
                $run(true);
            });
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            $_ = clone $evil;

            return $mock::TAG;
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testCloneMutatingEscapedNullBeforeCoalesceAssignThrows(): void
    {
        $value = null;
        $evil = new RuntimeEvilCloner(static function () use (&$value): void {
            $value = ProvenanceRuntimeTrait::class;
        });
        $value = null;
        $_ = clone $evil;
        $value ??= Mockery::mock(ProvenanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testStaticLocalInitializerSideEffectThrowsOnSecondCall(): void
    {
        $run = static function (): string {
            $other = ProvenanceRuntimeTrait::class;
            static $once = ($other = Mockery::mock(ProvenanceRuntimeTrait::class));
            unset($once);

            return $other::TAG;
        };

        self::assertSame('tag', $run());

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run();
    }

    public function testUserPureFunctionReentryOverwritesStaticLocalAndThrows(): void
    {
        $run = null;
        $run = static function (bool $reset) use (&$run): string {
            static $mock;
            if ($reset) {
                $mock = ProvenanceRuntimeTrait::class;

                return 'reset';
            }
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            runtimeFakePureCaller(static function () use (&$run): void {
                $run(true);
            });

            return $mock::TAG;
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testOverridablePureMethodReentryOverwritesStaticLocalAndThrows(): void
    {
        $sub = new RuntimePureSub();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $sub->runWithStaticLocal(false);
    }

    public function testBuiltinPureImplodeWithStringableReentryThrows(): void
    {
        $run = null;
        $run = static function (bool $reset) use (&$run): string {
            static $mock;
            if ($reset) {
                $mock = ProvenanceRuntimeTrait::class;

                return 'reset';
            }
            $evil = new RuntimeEvilStringable(static function () use (&$run): void {
                $run(true);
            });
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            implode('', [$evil]);

            return $mock::TAG;
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testMockeryFactoryConstructorReentryOverwritesStaticLocalAndThrows(): void
    {
        $run = null;
        $run = static function (bool $reset) use (&$run): string {
            static $mock;
            if ($reset) {
                $mock = ProvenanceRuntimeTrait::class;

                return 'reset';
            }
            $cb = static function () use (&$run): void {
                $run(true);
            };
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            Mockery::mock(RuntimeCtorInvoker::class, [$cb]);

            return $mock::TAG;
        };

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(false);
    }

    public function testStaticLocalWithSafeShouldReceiveAndFactoryPreservesTraitConstantAccess(): void
    {
        $run = static function (): string {
            static $mock;
            $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            $mock->shouldReceive('ping')->andReturn('pong');
            Mockery::mock(\stdClass::class);

            return $mock::TAG;
        };

        self::assertSame('tag', $run());
    }

    public function testDoWhileFalseWithNestedSwitchBreakDominatesTraitConstantAccess(): void
    {
        $run = static function (int $x): string {
            $mock = ProvenanceRuntimeTrait::class;
            do {
                switch ($x) {
                    case 1:
                        break;
                    default:
                        break;
                }
                $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            } while (false);

            return $mock::TAG;
        };

        self::assertSame('tag', $run(1));
        self::assertSame('tag', $run(2));
    }

    public function testDoWhileFalseWithNestedSwitchBreakTwoThrowsOnOuterExit(): void
    {
        $run = static function (int $x): string {
            $mock = ProvenanceRuntimeTrait::class;
            do {
                switch ($x) {
                    case 1:
                        break 2;
                }
                $mock = Mockery::mock(ProvenanceRuntimeTrait::class);
            } while (false);

            return $mock::TAG;
        };

        self::assertSame('tag', $run(2));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(1);
    }
}
