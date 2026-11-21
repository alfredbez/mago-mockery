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

        $spacedPartial = Mockery::mock(ApiClient::class . ' [fetch]');
        $spacedPartial->fetch(2);
        $this->acceptApiClient($spacedPartial);

        $pipePartial = Mockery::mock(ApiClient::class . '[fetch]|' . Greeter::class);
        $pipePartial->fetch(3);
        $pipePartial->greet('pipe');
        $this->acceptApiClient($pipePartial);

        $separatePartialAndIface = Mockery::mock(ApiClient::class . '[fetch]', Greeter::class);
        $separatePartialAndIface->fetch(4);
        $separatePartialAndIface->greet('separate');
        $this->acceptApiClient($separatePartialAndIface);

        $aliasWithPipe = Mockery::mock('alias:' . ApiClient::class . '|' . Greeter::class);
        $aliasWithPipe->fetch(5);
        $aliasWithPipe->greet('alias-pipe');

        $overloadWithIface = Mockery::mock('overload:' . ApiClient::class, Greeter::class);
        $overloadWithIface->fetch(6);
        $overloadWithIface->greet('overload-iface');
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

        $allCombined = Mockery::mock(
            ApiClient::class,
            Greeter::class,
            Alpha::class,
            [1, 2],
            ['greet' => 'quick'],
            static function (MockInterface $mock): void {
                $mock->shouldReceive('fetch')->andReturn('configured');
            },
        );
        $allCombined->fetch(7);
        $allCombined->greet('all');
        $allCombined->alpha();
        $this->acceptApiClient($allCombined);
    }

    public function objectPartialNamedAndInstanceMock(): void
    {
        $objectPartial = Mockery::mock(new ApiClient());
        $objectPartial->fetch(1);
        $this->acceptApiClient($objectPartial);

        $objectWithInterface = Mockery::mock(new GreeterImpl(), Alpha::class);
        $objectWithInterface->greet('obj');
        $objectWithInterface->alpha();

        $named = namedMock('CustomAlias', Greeter::class, Alpha::class);
        $named->greet('world');
        $named->alpha();

        $instance = Mockery::instanceMock(ApiClient::class);
        $instance->fetch(1);
        $this->acceptApiClient($instance);

        $traitMock = Mockery::mock(LoggableTrait::class);
        $traitMock->logTraitMessage('from-trait');
        $traitMock->shouldReceive('logTraitMessage')->with('from-trait')->andReturn('ok');
        $traitTag = $traitMock::TAG;
        unset($traitTag);

        $fetched = Mockery::fetchMock(Greeter::class);
        if ($fetched !== null) {
            $fetched->greet('fetched');
            $this->acceptGreeter($fetched);
        }

        $containerFetched = Mockery::getContainer()->fetchMock(reference: ApiClient::class);
        if ($containerFetched !== null) {
            $containerFetched->fetch(42);
            $this->acceptApiClient($containerFetched);
        }
    }
}
