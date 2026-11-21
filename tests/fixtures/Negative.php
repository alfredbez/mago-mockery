<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\Expectation;

/**
 * The intersection must narrow, not silence. Each case states its expectation.
 *
 * Known boundary: literal method names passed to `shouldReceive('doesNotExist')`
 * or HigherOrderMessage (`shouldReceive()->doesNotExist()`) are not validated
 * against the mocked class's method list.
 */
final class Negative extends Support
{
    /**
     * A mock is not a licence for unknown methods.
     */
    public function unknownMethodOnMock(): void
    {
        Mockery::mock(Greeter::class)->missingMethod();
    }

    /**
     * Unknown methods on a CompositeExpectation chain must not be swallowed.
     */
    public function unknownMethodOnCompositeExpectation(): void
    {
        Mockery::mock(Greeter::class)->shouldReceive('greet')->totallyFakeExpectationMethod('arg');
    }

    /**
     * Forwarded Expectation methods must preserve their parameter types.
     */
    public function invalidArgumentTypeOnCompositeExpectation(Expectation $expectation): void
    {
        $greeter = Mockery::mock(Greeter::class);
        $greeter->shouldReceive('greet')->times('not-an-int');
        $greeter->shouldReceive('greet')->andReturnArg(-1);
        $greeter->shouldReceive('greet')->andReturnValues('not-an-array');
        $greeter->shouldReceive('greet')->withArgs(123);
        $greeter->shouldHaveReceived('greet')->withArgs(123);
        $expectation->andReturnArg(-1);
        $expectation->withArgs(123);
    }

    /**
     * Matcher factories must reject invalid argument types.
     */
    public function invalidArgumentTypesOnMatchers(): void
    {
        Mockery::on('not-a-closure');
        Mockery::subset('not-an-array');
        Mockery::type(123);
        Mockery::pattern(123);
        Mockery::ducktype(123);
        Mockery::hasKey([]);
    }

    /**
     * CompositeExpectation::add() returns void, so chaining after add() must fail.
     */
    public function chainingAfterCompositeAdd(Expectation $expectation): void
    {
        Mockery::mock(Greeter::class)->shouldReceive('greet')->add($expectation)->andReturn('x');
    }

    /**
     * getMock() preserves the mocked type so unknown methods on it still fail.
     */
    public function unknownMethodAfterGetMock(): void
    {
        Mockery::mock(Greeter::class)->shouldReceive('greet')->andReturn('hi')->getMock()->missingAfterGetMock();
    }

    /**
     * A Greeter mock is not an ApiClient.
     */
    public function greeterMockIntoApiClient(): void
    {
        $this->acceptApiClient(Mockery::mock(Greeter::class));
    }

    /**
     * shouldNotHaveReceived('name') returns void/null at runtime; chaining fails.
     */
    public function chainingAfterShouldNotHaveReceived(): void
    {
        Mockery::mock(Greeter::class)->shouldNotHaveReceived('countGreetings')->withAnyArgs();
    }

    /**
     * shouldNotHaveBeenCalled() returns void/null at runtime; chaining fails.
     */
    public function chainingAfterShouldNotHaveBeenCalled(): void
    {
        Mockery::mock(Greeter::class)->shouldNotHaveBeenCalled()->once();
    }

    /**
     * Higher-order shouldNotHaveReceived()->greet() returns void/null at runtime.
     */
    public function chainingAfterHigherOrderShouldNotHaveReceived(): void
    {
        Mockery::mock(Greeter::class)->shouldNotHaveReceived()->greet('x')->once();
    }

    /**
     * Higher-order shouldHaveReceived()->greet() returns VerificationDirector, not Expectation.
     */
    public function expectationMethodOnHigherOrderVerification(): void
    {
        Mockery::mock(Greeter::class)->shouldHaveReceived()->greet('x')->andReturn('hi');
    }

    /**
     * Fluent mock builders preserve the mocked type, so unknown methods still fail.
     */
    public function unknownMethodAfterFluentBuilders(): void
    {
        Mockery::mock(Greeter::class)->makePartial()->missingAfterPartial();
        Mockery::mock(Greeter::class)->allows(['greet' => 'hi'])->missingAfterAllowsMap();
    }

    /**
     * Higher-order getMock() preserves the mocked type, so unknown methods still fail.
     */
    public function unknownMethodAfterHigherOrderGetMock(): void
    {
        Mockery::mock(Greeter::class)->shouldReceive()->greet('x')->andReturn('hi')->getMock()->missingAfterHigherOrder();
    }

    /**
     * String specs and object partial mocks still reject unknown methods and wrong types.
     */
    public function invalidUsageOnStringSpecsAndObjectMocks(): void
    {
        $this->acceptApiClient(Mockery::mock('overload:' . Greeter::class));
        Mockery::mock(ApiClient::class . '[fetch]')->missingOnPartialSpec();
        Mockery::mock(new GreeterImpl())->missingOnObjectPartial();
        Mockery::mock('overload:MockeryFixture\\NonExistentClass')->missingOnUnknownOverload();
    }

    /**
     * KNOWN GAP (Mago 1.60.2): an extension-supplied intersection compared
     * against an intersection parameter is accepted. This call must report
     * "argument #1 expects Greeter&MockInterface, found ApiClient&MockInterface"
     * once Mago compares intersections member by member.
     */
    public function apiClientMockIntoGreeter(): void
    {
        $this->acceptGreeter(Mockery::mock(ApiClient::class));
    }
}
