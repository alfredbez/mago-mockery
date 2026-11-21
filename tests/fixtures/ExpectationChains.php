<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\Expectation;
use Mockery\Matcher\Any;
use Mockery\Matcher\AnyOf;
use Mockery\Matcher\AndAnyOtherArgs;
use Mockery\Matcher\Closure as ClosureMatcher;
use Mockery\Matcher\Contains;
use Mockery\Matcher\HasKey;
use Mockery\Matcher\HasValue;
use Mockery\Matcher\IsEqual;
use Mockery\Matcher\IsSame;
use Mockery\Matcher\MustBe;
use Mockery\Matcher\Not;
use Mockery\Matcher\NotAnyOf;
use Mockery\Matcher\Subset;
use Mockery\Matcher\Type as TypeMatcher;
use RuntimeException;

/**
 * Higher-order messages: shouldReceive() and expects() without a method name
 * return HigherOrderMessage / ExpectsHigherOrderMessage, whose __call forwards
 * to an Expectation. These chains must stay silent with the extension.
 */
final class ExpectationChains
{
    private mixed $captured = null;
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
        $greeter->shouldHaveReceived('greet')->withArgs(['x'])->once();
        $greeter->shouldHaveReceived('greet')->withArgs(static fn (string $name): bool => $name === 'x')->once();
        $greeter->shouldHaveBeenCalled()->once();
    }

    public function expectationMethodsAndMatchers(Expectation $expectation): void
    {
        $greeter = Mockery::mock(Greeter::class);

        $greeter->shouldReceive('greet')
            ->andReturnUsing(static fn (string $name): string => $name)
            ->andReturnValues(['a', 'b'])
            ->andReturnSelf()
            ->andReturnArg(0)
            ->andSet('label', 'first', 'second')
            ->withArgs(['world'])
            ->withArgs(static fn (string $name): bool => $name !== '')
            ->withSomeOfArgs('world')
            ->getMock()
            ->greet('world');

        $expectation
            ->andReturnUsing(static fn (string $name): string => $name)
            ->andReturnValues(['a', 'b'])
            ->andReturnSelf()
            ->andReturnArg(0)
            ->andSet('label', 'first', 'second')
            ->withArgs(['world'])
            ->withArgs(static fn (string $name): bool => $name !== '')
            ->withSomeOfArgs('world');

        $on = Mockery::on(static fn (string $name): bool => $name !== '');
        $capture = Mockery::capture($this->captured);
        $subset = Mockery::subset(['a' => 1]);
        $subsetLoose = Mockery::subset(['a' => 1], false);
        $type = Mockery::type('string');
        $any = Mockery::any();
        $mustBe = Mockery::mustBe('exact');
        $contains = Mockery::contains('a', 'b');
        $hasKey = Mockery::hasKey('id');
        $hasValue = Mockery::hasValue(1);
        $isEqual = Mockery::isEqual('expected');
        $isSame = Mockery::isSame('expected');
        $anyOf = Mockery::anyOf('a', 'b');
        $notAnyOf = Mockery::notAnyOf('c', 'd');
        $not = Mockery::not('e');
        $andAnyOthers = Mockery::andAnyOtherArgs();

        $this->acceptMatchers($on, $capture, $subset, $subsetLoose, $type, $any, $mustBe, $contains, $hasKey, $hasValue);
        $this->acceptExtendedMatchers($isEqual, $isSame, $anyOf, $notAnyOf, $not, $andAnyOthers);

        $greeter->shouldReceive('greet')->with($on)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($capture)->andReturn('ok');
        $greeter->shouldReceive('greet')->withArgs([$subset])->andReturn('ok');
        $greeter->shouldReceive('greet')->with(Mockery::pattern('/^hi/'))->andReturn('ok');
        $greeter->shouldReceive('greet')->with(Mockery::ducktype('greet'))->andReturn('ok');
        $greeter->shouldReceive('greet')->with($type)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($any)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($mustBe)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($contains)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($hasKey)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($hasValue)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($isEqual)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($isSame)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($anyOf)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($notAnyOf)->andReturn('ok');
        $greeter->shouldReceive('greet')->with($not)->andReturn('ok');
        $greeter->shouldReceive('greet')->with('world', $andAnyOthers)->andReturn('ok');
    }

    private function acceptMatchers(
        ClosureMatcher $on,
        ClosureMatcher $capture,
        Subset $subset,
        Subset $subsetLoose,
        TypeMatcher $type,
        Any $any,
        MustBe $mustBe,
        Contains $contains,
        HasKey $hasKey,
        HasValue $hasValue,
    ): void {
        unset($on, $capture, $subset, $subsetLoose, $type, $any, $mustBe, $contains, $hasKey, $hasValue);
    }

    private function acceptExtendedMatchers(
        IsEqual $isEqual,
        IsSame $isSame,
        AnyOf $anyOf,
        NotAnyOf $notAnyOf,
        Not $not,
        AndAnyOtherArgs $andAnyOthers,
    ): void {
        unset($isEqual, $isSame, $anyOf, $notAnyOf, $not, $andAnyOthers);
    }
}
