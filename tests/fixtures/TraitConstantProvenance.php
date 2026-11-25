<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;

use function strlen;

final class TraitConstantProvenance
{
    public function staticLocalWithPureCall(): string
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        strlen('unrelated');

        return $mock::TAG;
    }

    public function staticLocalWithoutCall(): string
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function plainLocalWithPureCall(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        strlen('unrelated');

        return $mock::TAG;
    }

    public function staticLocalAcrossPureLoop(): string
    {
        static $mock;
        $mock = Mockery::mock(LoggableTrait::class);
        for ($i = 0; $i < 2; ++$i) {
            strlen('unrelated');
        }

        return $mock::TAG;
    }

    public function doWhileFalseFirstIteration(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $tag = $mock::TAG;
            $mock = LoggableTrait::class;
        } while (false);

        return $tag;
    }

    public function doWhileZeroFirstIteration(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        do {
            $tag = $mock::TAG;
            $mock = LoggableTrait::class;
        } while (0);

        return $tag;
    }

    public function doWhileFalsePostLoopMock(): string
    {
        $mock = LoggableTrait::class;
        do {
            $mock = Mockery::mock(LoggableTrait::class);
        } while (false);

        return $mock::TAG;
    }

    public function doWhileFalseEarlyBreakGuard(bool $flag): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $tag = 'skipped';
        do {
            if ($flag) {
                $mock = LoggableTrait::class;
                break;
            }
            $tag = $mock::TAG;
            $mock = LoggableTrait::class;
        } while (false);

        return $tag;
    }

    public function doWhileReassignedBeforeAccess(bool $cond): string
    {
        do {
            $mock = Mockery::mock(LoggableTrait::class);
            $tag = $mock::TAG;
            $mock = LoggableTrait::class;
        } while ($cond);

        return $tag;
    }

    public function fluentShouldDeferMissing(): string
    {
        $mock = Mockery::mock(LoggableTrait::class)->shouldDeferMissing();

        return $mock::TAG;
    }

    public function fluentKnownBuilders(): string
    {
        $mock = Mockery::mock(LoggableTrait::class)
            ->makePartial()
            ->shouldIgnoreMissing()
            ->byDefault()
            ->shouldAllowMockingProtectedMethods()
            ->shouldAllowMockingMethod('dynamicHelper');

        return $mock::TAG;
    }

    public function fluentAsUndefinedControl(): string
    {
        $mock = Mockery::mock(LoggableTrait::class)->asUndefined();

        return $mock::TAG;
    }

    public function strictAllowanceAcrossShouldDeferMissing(): void
    {
        $greeter = Mockery::mock(Greeter::class);
        $greeter->shouldAllowMockingMethod('dynamicAfterDefer');
        $partial = $greeter->shouldDeferMissing();
        $partial->shouldReceive('dynamicAfterDefer');
    }

    public function cloneDirectMock(): string
    {
        $clone = clone Mockery::mock(LoggableTrait::class);

        return $clone::TAG;
    }

    public function cloneIndirectMock(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $clone = clone $mock;
        $mock = LoggableTrait::class;

        return $clone::TAG;
    }

    public function cloneParenthesizedAccess(): string
    {
        return (clone Mockery::mock(LoggableTrait::class))::TAG;
    }

    public function cloneConditionalAllMocks(bool $flag): string
    {
        $clone = $flag
            ? clone Mockery::mock(LoggableTrait::class)
            : clone Mockery::spy(LoggableTrait::class);

        return $clone::TAG;
    }

    public function cloneChainedFluent(): string
    {
        $clone = (clone Mockery::mock(LoggableTrait::class))->makePartial();

        return $clone::TAG;
    }

    public function coalesceAssignFromKnownNull(): string
    {
        $mock = null;
        $mock ??= Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function coalesceAssignFromKnownMock(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $mock ??= LoggableTrait::class;

        return $mock::TAG;
    }

    public function coalesceAssignFromNullableMockTernary(bool $flag): string
    {
        $mock = $flag ? Mockery::mock(LoggableTrait::class) : null;
        $mock ??= Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function coalesceAssignFromNullableMockIf(bool $flag): string
    {
        $mock = null;
        if ($flag) {
            $mock = Mockery::mock(LoggableTrait::class);
        }
        $mock ??= Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function coalesceAssignRepeated(bool $flag): string
    {
        $mock = null;
        if ($flag) {
            $mock ??= Mockery::mock(LoggableTrait::class);
        }
        $mock ??= Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function coalesceAssignAfterUnset(): string
    {
        $mock = LoggableTrait::class;
        unset($mock);
        $mock ??= Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function coalesceExprFromNullableMock(bool $flag): string
    {
        $mock = $flag ? Mockery::mock(LoggableTrait::class) : null;
        $resolved = $mock ?? Mockery::mock(LoggableTrait::class);

        return $resolved::TAG;
    }
}
