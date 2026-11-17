<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use RuntimeException;

/**
 * Higher-order messages: shouldReceive() and expects() without a method name
 * return HigherOrderMessage / ExpectsHigherOrderMessage, whose __call forwards
 * to an Expectation. These chains must stay silent with the extension.
 */
final class ExpectationChains
{
    public function higherOrderMessages(): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $greeter->shouldReceive()->greet('x')->once()->andReturn('hi');
        $greeter->expects()->greet('x')->once()->andReturn('hi');
        $greeter->shouldReceive()->withAnyArgs()->andReturn('anything');
        $greeter->shouldHaveReceived()->greet('x')->once();
        $greeter->shouldNotReceive()->greet('x');
        $greeter->shouldNotHaveReceived()->greet('x');
    }

    public function declaredChainMethods(): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $greeter->shouldReceive('greet')->with('x')->andReturn('hi');
        $greeter->shouldReceive('greet')->andThrow(new RuntimeException('nope'));
        $greeter->shouldReceive(['greet' => 'hi', 'countGreetings' => 1]);
        $greeter->shouldIgnoreMissing();
        $greeter->allows('greet')->between(1, 2);
        $greeter->expects('greet')->twice()->andReturn('hi');
        $greeter->shouldNotReceive('greet');
        $greeter->shouldHaveReceived('greet');
    }

    /**
     * shouldReceive('name') returns a CompositeExpectation, so the chain can
     * continue with another shouldReceive() after andReturn().
     */
    public function compositeExpectationChains(): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $mockFromGetMock = $greeter->shouldReceive('greet')
            ->andReturn('hi')
            ->shouldReceive('countGreetings')
            ->andReturn(1)
            ->getMock();
        $mockFromGetMock->greet('world');

        $mockFromAlias = $greeter->shouldReceive('greet')->andReturn('hi')->mock();
        $mockFromAlias->countGreetings();

        $greeter->shouldReceive('greet')->with('x')->once()->andReturn('hi')->twice();

        // shouldHaveReceived() returns a VerificationDirector at runtime.
        $greeter->shouldHaveReceived('greet')->with('x')->once();
        $greeter->shouldNotHaveReceived('countGreetings')->withAnyArgs();
        $greeter->shouldHaveBeenCalled();
    }
}
