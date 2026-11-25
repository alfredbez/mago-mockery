<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Error;
use Mockery;
use PHPUnit\Framework\TestCase;

trait DominanceRuntimeTrait
{
    public const TAG = 'tag';
}

function overwriteRuntimeTraitReceiver(mixed &$target): void
{
    $target = DominanceRuntimeTrait::class;
}

final class RuntimeNullsafeSink
{
    public function accept(mixed $value): void
    {
        unset($value);
    }
}

final class TraitConstantDominanceAndAliasesRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testConditionalMockReassignmentSucceedsOnMockPath(): void
    {
        self::assertSame('tag', self::conditionalMockOrPlain(true));
    }

    public function testConditionalMockReassignmentThrowsOnPlainPath(): void
    {
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        self::conditionalMockOrPlain(false);
    }

    public function testBothBranchesMockAndPostConditionalMockSucceed(): void
    {
        foreach ([true, false] as $flag) {
            if ($flag) {
                $value = Mockery::mock(DominanceRuntimeTrait::class);
            } else {
                $value = Mockery::spy(DominanceRuntimeTrait::class);
            }
            self::assertSame('tag', $value::TAG);

            $overwritten = DominanceRuntimeTrait::class;
            if ($flag) {
                $overwritten = Mockery::mock(DominanceRuntimeTrait::class);
            }
            $overwritten = Mockery::mock(DominanceRuntimeTrait::class);
            self::assertSame('tag', $overwritten::TAG);
        }
    }

    public function testByReferenceMockAliasReturnsConstant(): void
    {
        $mock = Mockery::mock(DominanceRuntimeTrait::class);
        $alias = &$mock;

        self::assertSame('tag', $alias::TAG);
    }

    public function testByReferenceNonMockAliasThrows(): void
    {
        $plain = DominanceRuntimeTrait::class;
        $alias = &$plain;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $alias::TAG;
    }

    public function testByReferenceAliasInvalidatedByOriginalThrows(): void
    {
        $mock = Mockery::mock(DominanceRuntimeTrait::class);
        $alias = &$mock;
        $mock = DominanceRuntimeTrait::class;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $alias::TAG;
    }

    public function testPlainCopyAndUnsetAliasPreserveMock(): void
    {
        $mock = Mockery::mock(DominanceRuntimeTrait::class);
        $copy = $mock;
        $mock = DominanceRuntimeTrait::class;
        self::assertSame('tag', $copy::TAG);

        $second = Mockery::mock(DominanceRuntimeTrait::class);
        $refAlias = &$second;
        unset($refAlias);
        self::assertSame('tag', $second::TAG);
    }

    public function testReferenceCyclesMatchRuntimeSemantics(): void
    {
        $a = Mockery::mock(DominanceRuntimeTrait::class);
        $b = &$a;
        $a = &$b;
        self::assertSame('tag', $b::TAG);

        $plainA = DominanceRuntimeTrait::class;
        $plainB = &$plainA;
        $plainA = &$plainB;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $plainB::TAG;
    }

    public function testConditionalReferenceAliasThrowsWhenAliasedBranchInvalidated(): void
    {
        $unaliased = static function (bool $flag): string {
            $a = Mockery::mock(DominanceRuntimeTrait::class);
            $b = Mockery::mock(DominanceRuntimeTrait::class);
            if ($flag) {
                $b = &$a;
            }
            $a = DominanceRuntimeTrait::class;

            return $b::TAG;
        };

        self::assertSame('tag', $unaliased(false));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $unaliased(true);
    }

    public function testLoopBackEdgeInvalidationThrowsOnSecondIteration(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        for ($i = 0; $i < 2; ++$i) {
            $_unused = $value::TAG;
            $value = DominanceRuntimeTrait::class;
        }
    }

    public function testByReferenceFunctionCallInvalidationThrows(): void
    {
        $mock = Mockery::mock(DominanceRuntimeTrait::class);
        overwriteRuntimeTraitReceiver($mock);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $mock::TAG;
    }

    public function testNullsafeConditionalMockThrowsWhenReceiverIsNull(): void
    {
        $run = static function (?RuntimeNullsafeSink $sink): string {
            $value = DominanceRuntimeTrait::class;
            $sink?->accept($value = Mockery::mock(DominanceRuntimeTrait::class));

            return $value::TAG;
        };

        self::assertSame('tag', $run(new RuntimeNullsafeSink()));

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $run(null);
    }

    public function testUnparenthesizedTernaryInvalidationThrows(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);
        $flag = true;
        $flag ? $value = DominanceRuntimeTrait::class : null;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testMatchArmConditionInvalidationThrows(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = match (DominanceRuntimeTrait::class) {
            ($value = DominanceRuntimeTrait::class) => $value::TAG,
        };
    }

    public function testArrayEscapedReferenceInvalidationThrows(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);
        $box = [];
        $box[0] = &$value;
        $box[0] = DominanceRuntimeTrait::class;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testForeachEscapedReferenceInvalidationThrows(): void
    {
        $box = [DominanceRuntimeTrait::class];
        foreach ($box as &$value) {
        }
        $value = Mockery::mock(DominanceRuntimeTrait::class);
        $box[0] = DominanceRuntimeTrait::class;

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testClosureCapturedReferenceInvalidationPersistsAfterReassignment(): void
    {
        $value = DominanceRuntimeTrait::class;
        $reset = static function () use (&$value): void {
            $value = DominanceRuntimeTrait::class;
        };
        $value = Mockery::mock(DominanceRuntimeTrait::class);
        $reset();

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testComplexUnsetTargetSideEffectThrows(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);
        $xs = [];
        unset($xs[$value = DominanceRuntimeTrait::class]);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        $_unused = $value::TAG;
    }

    public function testAnonymousClassConstructorArgInLoopThrowsOnSecondIteration(): void
    {
        $value = Mockery::mock(DominanceRuntimeTrait::class);

        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        for ($i = 0; $i < 2; ++$i) {
            $_unused = $value::TAG;
            new class($value = DominanceRuntimeTrait::class) {
                public function __construct(mixed $ignored)
                {
                    unset($ignored);
                }
            };
        }
    }

    private static function conditionalMockOrPlain(bool $mockPath): string
    {
        $value = DominanceRuntimeTrait::class;
        if ($mockPath) {
            $value = Mockery::mock(DominanceRuntimeTrait::class);
        }

        return $value::TAG;
    }
}
