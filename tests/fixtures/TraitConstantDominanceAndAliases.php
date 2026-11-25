<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\Container;

function inspectTraitReceiver(mixed $value): void
{
    unset($value);
}

final class TraitConstantDominanceAndAliases
{
    public function straightLineMock(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);

        return $mock::TAG;
    }

    public function bothBranchesMock(bool $flag): string
    {
        if ($flag) {
            $value = Mockery::mock(LoggableTrait::class);
        } else {
            $value = Mockery::spy(LoggableTrait::class);
        }

        return $value::TAG;
    }

    public function earlyReturnGuard(bool $mockPath): string
    {
        $value = LoggableTrait::class;
        if (!$mockPath) {
            return 'fallback';
        }
        $value = Mockery::mock(LoggableTrait::class);

        return $value::TAG;
    }

    public function postConditionalReassignmentToMock(bool $flag): string
    {
        $value = LoggableTrait::class;
        if ($flag) {
            $value = Mockery::mock(LoggableTrait::class);
        }
        $value = Mockery::mock(LoggableTrait::class);

        return $value::TAG;
    }

    public function byReferenceMockAlias(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $alias = &$mock;

        return $alias::TAG;
    }

    public function plainCopyUnaffectedByOriginalReassignment(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $copy = $mock;
        $mock = LoggableTrait::class;
        unset($mock);

        return $copy::TAG;
    }

    public function unsetReferenceAliasPreservesOriginal(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $alias = &$mock;
        unset($alias);

        return $mock::TAG;
    }

    public function referenceCycleMock(): string
    {
        $a = Mockery::mock(LoggableTrait::class);
        $b = &$a;
        $a = &$b;

        return $b::TAG;
    }

    public function containerParameterAndParenthesizedFactory(Container $container): string
    {
        $fromParam = $container->mock(LoggableTrait::class);
        $direct = ($container->mock(LoggableTrait::class))::TAG;

        return $fromParam::TAG . $direct;
    }

    public function priorClosureDoesNotHideOuterMock(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        $helper = static function (): string {
            return LoggableTrait::class;
        };
        unset($helper);

        return $mock::TAG;
    }

    public function byValueCallsPreserveMock(): string
    {
        $mock = Mockery::mock(LoggableTrait::class);
        inspectTraitReceiver($mock);
        $this->inspectMethod($mock);

        return $mock::TAG;
    }

    public function matchAllArmsMock(bool $flag): string
    {
        $mock = match ($flag) {
            true => Mockery::mock(LoggableTrait::class),
            false => Mockery::spy(LoggableTrait::class),
        };

        return $mock::TAG;
    }

    private function inspectMethod(mixed $value): void
    {
        unset($value);
    }
}
