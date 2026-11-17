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
    public function invalidArgumentTypeOnCompositeExpectation(): void
    {
        Mockery::mock(Greeter::class)->shouldReceive('greet')->times('not-an-int');
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
