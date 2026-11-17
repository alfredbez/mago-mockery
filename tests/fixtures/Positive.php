<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;

/**
 * Every line here is valid Mockery and must stay silent with the extension.
 */
final class Positive extends Support
{
    public function mockFactories(): void
    {
        $greeter = Mockery::mock(Greeter::class);
        $greeter->greet('world');
        $greeter->countGreetings();
        $this->acceptGreeter($greeter);

        $client = mock(ApiClient::class);
        $client->fetch(1);
        $client->shouldReceive('fetch')->andReturn('body');

        $spy = spy(Greeter::class);
        $spy->countGreetings();

        $named = Mockery::namedMock('a-name', Greeter::class);
        $named->greet('world');

        $multi = Mockery::mock(Greeter::class, ApiClient::class);
        $multi->greet('world');
        $multi->fetch(1);
    }

    public function expectationChains(): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $greeter->shouldReceive('greet')->with('x')->andReturn('hi')->once();
        $greeter->shouldReceive('greet')->andReturnUsing(static fn (string $name): string => $name);
        $greeter->allows('greet')->andReturn('hi');
        $greeter->allows(['greet' => 'hi']);
        $greeter->expects('greet')->andReturn('hi');
    }
}
