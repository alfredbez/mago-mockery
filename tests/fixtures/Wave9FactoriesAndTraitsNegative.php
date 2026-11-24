<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

final class FakeTraitFactory
{
    /** @return class-string<LoggableTrait> */
    public function mock(): string
    {
        return LoggableTrait::class;
    }
}

final class Wave9FactoriesAndTraitsNegative extends Support
{
    /**
     * @param class-string<Greeter> $greeterClass
     */
    public function classStringMissingMethod(string $greeterClass): void
    {
        $typed = Mockery::mock($greeterClass);
        $typed->missingOnClassString();
    }

    public function namedMockFetchControls(): void
    {
        Mockery::namedMock('Wave9NegativeAlias', Greeter::class);
        $fetched = Mockery::fetchMock('Wave9NegativeAlias');
        if ($fetched !== null) {
            $fetched->missingOnNamedFetch();
        }

        $crossFile = Mockery::fetchMock('Wave9CrossFileAlias');
        if ($crossFile !== null) {
            $crossFile->greet('must-stay-unresolved-across-files');
        }

        $unknown = Mockery::fetchMock('Wave9NeverRegistered');
        if ($unknown !== null) {
            $unknown->greet('must-stay-unresolved-unknown');
        }
    }

    public function higherOrderUnknownMethod(): void
    {
        $greeter = Mockery::mock(Greeter::class);
        $greeter->shouldReceive()->missingHigherOrderMethod('bad')->andReturn('nope');
    }

    public function staleTraitClassStringAndPlainControl(): void
    {
        $traitMock = Mockery::mock(LoggableTrait::class);
        $ok = $traitMock::TAG;
        $traitMock = LoggableTrait::class;
        $stale = $traitMock::TAG;

        $plain = LoggableTrait::class;
        $direct = $plain::TAG;

        $fakeFactory = new FakeTraitFactory();
        $fakeTag = ($fakeFactory->mock())::TAG;
        unset($ok, $stale, $direct, $fakeTag);
    }

    public function broadFactoryWrapperControl(Wave9FactoriesAndTraits $factory): void
    {
        $wrapped = $factory->broadFactoryControl();
        $wrapped->greet('wrapper-returns-mock-interface-only');
    }

    /**
     * @param class-string $bareClass
     */
    public function bareClassStringControl(string $bareClass): void
    {
        $bare = Mockery::mock($bareClass);
        $bare->greet('bare-class-string-keeps-baseline-parity');
    }
}
