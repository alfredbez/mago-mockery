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
        $greeter->allows()->greet('x')->andReturns('hi');
        $greeter->allows([])->greet('x')->andReturns('hi');
        $greeter->expects()->greet('x')->once()->andReturn('hi');
        $greeter->expects(null)->greet('x')->andReturn('hi');
        $greeter->shouldReceive()->withAnyArgs()->andReturn('anything');
        $greeter->shouldHaveReceived()->greet('x')->once();
        $greeter->shouldHaveReceived(null)->greet('x')->once();
        $greeter->shouldNotReceive()->greet('x');
        $greeter->shouldNotHaveReceived()->greet('x');
        $greeter->shouldNotHaveReceived(null)->greet('x');

        $fromHigherOrder = $greeter->shouldReceive()
            ->greet('x')
            ->andReturn('hi')
            ->shouldReceive('countGreetings')
            ->andReturn(1)
            ->getMock();
        $fromHigherOrder->greet('world');
    }

    public function declaredChainMethods(string $dynamicMethod): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $greeter->shouldReceive('greet')->with('x')->andReturn('hi');
        $greeter->shouldReceive('greet')->andThrow(new RuntimeException('nope'));
        $greeter->shouldReceive('greet')->andReturnSelf()->byDefault()->passthru();
        $greeter->shouldReceive(['greet' => 'hi', 'countGreetings' => 1]);
        $greeter->shouldIgnoreMissing();
        $greeter->allows('greet')->between(1, 2);
        $greeter->allows($dynamicMethod)->andReturn('hi')->shouldReceive('countGreetings')->andReturn(1)->getMock()->greet('world');
        $greeter->expects('greet')->twice()->andReturn('hi');
        $greeter->expects($dynamicMethod)->andReturn('hi')->getMock()->countGreetings();
        $greeter->shouldNotReceive('greet');
        $greeter->shouldHaveReceived('greet');
        $greeter->shouldNotHaveReceived('countGreetings');
        $greeter->shouldNotHaveReceived('countGreetings', ['x']);
        $greeter->shouldNotHaveBeenCalled();
    }

    public function fluentMockBuilders(): void
    {
        $partial = Mockery::mock(Greeter::class)->makePartial();
        $partial->greet('partial');

        $ignored = Mockery::mock(Greeter::class)->shouldIgnoreMissing();
        $ignored->greet('ignored');

        $protected = Mockery::mock(Greeter::class)->shouldAllowMockingProtectedMethods();
        $protected->greet('protected');

        $defaulted = Mockery::mock(Greeter::class)->byDefault();
        $defaulted->greet('default');

        $allowedMap = Mockery::mock(Greeter::class)->allows(['greet' => 'hi']);
        $allowedMap->greet('mapped');
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

        // shouldHaveReceived() and shouldHaveBeenCalled() return a VerificationDirector at runtime.
        $greeter->shouldHaveReceived('greet')->with('x')->once();
        $greeter->shouldHaveReceived('greet', ['x'])->once();
        $greeter->shouldHaveBeenCalled()->once();
    }
}
