<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

/**
 * Advanced Mockery mock factory specs harvested from phpstan-mockery and mockery-psalm-plugin:
 * alias/overload prefixes, partial method lists, comma-separated interfaces, constructor/quick
 * definition arrays, object partial mocks, namedMock(), and instanceMock().
 */
final class MockSpecs extends Support
{
    public function aliasAndOverload(): void
    {
        $alias = Mockery::mock('alias:' . ApiClient::class);
        $alias->fetch(1);
        $this->acceptApiClient($alias);

        $overload = Mockery::mock('overload:' . ApiClient::class);
        $overload->fetch(2);
        $this->acceptApiClient($overload);
    }

    public function partialMethodList(): void
    {
        $partial = Mockery::mock(ApiClient::class . '[fetch]', []);
        $partial->fetch(1);
        $this->acceptApiClient($partial);

        $excluded = Mockery::mock(Greeter::class . '[!countGreetings]');
        $excluded->greet('world');
        $this->acceptGreeter($excluded);
    }

    public function commaSeparatedAndConstructorArgs(): void
    {
        $combined = Mockery::mock(ApiClient::class, Greeter::class . ', ' . Alpha::class);
        $combined->fetch(1);
        $combined->greet('world');
        $combined->alpha();
        $this->acceptApiClient($combined);

        $withCtorArgs = Mockery::mock(ApiClient::class, [true]);
        $withCtorArgs->fetch(1);
        $this->acceptApiClient($withCtorArgs);

        $withQuickDefs = Mockery::mock(Greeter::class, ['greet' => 'hi']);
        $withQuickDefs->greet('world');

        $withClosure = Mockery::mock(Greeter::class, static function (MockInterface $mock): void {
            $mock->shouldReceive('greet')->andReturn('hi');
        });
        $withClosure->greet('world');
    }

    public function objectPartialNamedAndInstanceMock(): void
    {
        $objectPartial = Mockery::mock(new ApiClient());
        $objectPartial->fetch(1);
        $this->acceptApiClient($objectPartial);

        $named = namedMock('CustomAlias', Greeter::class, Alpha::class);
        $named->greet('world');
        $named->alpha();

        $instance = Mockery::instanceMock(ApiClient::class);
        $instance->fetch(1);
        $this->acceptApiClient($instance);
    }
}
