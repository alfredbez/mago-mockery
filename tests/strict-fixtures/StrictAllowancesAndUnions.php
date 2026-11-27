<?php

declare(strict_types=1);

namespace MockeryFixture\Strict;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

interface ChildMockHolder
{
    public function getChildMock(): StrictGreeter&MockInterface;
}

final class StrictMutator
{
    /**
     * @param StrictGreeter&MockInterface $mock
     */
    public function with(MockInterface &$mock): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
    }
}

final class StrictSut
{
    public function setGreeter(StrictGreeter $greeter): void
    {
        unset($greeter);
    }
}

class BaseGreeterWrapper
{
    public function __construct(StrictGreeter $greeter)
    {
        unset($greeter);
    }
}

final class ChildGreeterWrapper extends BaseGreeterWrapper {}

class ByRefGreeterWrapper
{
    /**
     * @param StrictGreeter&MockInterface $mock
     */
    public function __construct(MockInterface &$mock)
    {
        $mock = Mockery::mock(StrictGreeter::class);
    }
}

final class ChildByRefGreeterWrapper extends ByRefGreeterWrapper {}

final class NoCtorWrapper
{
    public int $tag = 1;
}

/**
 * @param StrictGreeter&MockInterface $mock
 */
function replaceMockByRef(MockInterface &$mock): void
{
    $mock = Mockery::mock(StrictGreeter::class);
}

/**
 * @param StrictGreeter&MockInterface $mock
 */
function inspectMockByValue(MockInterface $mock): void
{
    unset($mock);
}

final class StrictAllowancesAndUnionsFixture
{
    private StrictGreeter&MockInterface $prop;

    private function resetMock(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
    }

    public function helperMethodInvalidatesPropertyAllowance(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterHelper');
        $this->resetMock();
        $this->prop->shouldReceive('dynAfterHelper')->andReturn('x');
    }

    public function thisAliasInvalidatesPropertyAllowance(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterThisAlias');
        $self = $this;
        $self->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldReceive('dynAfterThisAlias')->andReturn('x');
    }

    public function nonFluentChainDoesNotCarryAllowance(): void
    {
        $holder = Mockery::mock(ChildMockHolder::class);
        $holder->shouldAllowMockingMethod('dynOnChild');
        $holder->getChildMock()->shouldReceive('dynOnChild')->andReturn('x');
    }

    public function byRefCallInvalidatesLocalAllowance(callable $unknownMutator): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynAfterByRef');
        replaceMockByRef($mock);
        $mock->shouldReceive('dynAfterByRef')->andReturn('x');

        $dynamicMock = Mockery::mock(StrictGreeter::class);
        $dynamicMock->shouldAllowMockingMethod('dynAfterUnknownCall');
        $unknownMutator($dynamicMock);
        $dynamicMock->shouldReceive('dynAfterUnknownCall')->andReturn('x');
    }

    public function byValueCallPreservesLocalAllowance(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynAfterByVal');
        inspectMockByValue($mock);
        $mock->shouldReceive('dynAfterByVal')->andReturn('ok');
    }

    public function fluentChainStillCarriesAllowance(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynFluent')->makePartial()->shouldReceive('dynFluent')->andReturn('ok');
    }

    public function literalUnionMethodNames(bool $cond, string $dynamicName): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldReceive($cond ? 'missingUnionA' : 'missingUnionB');
        $mock->shouldReceive($cond ? 'greet' : 'missingUnionPartial');
        $mock->shouldReceive($cond ? 'missingWithDynamic' : $dynamicName);
        $mock->shouldReceive($cond ? 'missingCase' : 'MISSINGCASE');
    }

    public function bareMethodNameWithByRefInvalidatesLocalAllowance(StrictMutator $mutator): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynAfterBareWith');
        $mutator->with($mock);
        $mock->shouldReceive('dynAfterBareWith')->andReturn('x');
    }

    public function unpackedByRefInvalidatesLocalAllowance(): void
    {
        $direct = Mockery::mock(StrictGreeter::class);
        $direct->shouldAllowMockingMethod('dynAfterUnpackInline');
        replaceMockByRef(...[&$direct]);
        if ($direct instanceof StrictGreeter && $direct instanceof MockInterface) {
            $direct->shouldReceive('dynAfterUnpackInline')->andReturn('x');
        }

        $indirect = Mockery::mock(StrictGreeter::class);
        $indirect->shouldAllowMockingMethod('dynAfterUnpackVar');
        $args = [&$indirect];
        replaceMockByRef(...$args);
        if ($indirect instanceof StrictGreeter && $indirect instanceof MockInterface) {
            $indirect->shouldReceive('dynAfterUnpackVar')->andReturn('x');
        }
    }

    public function inheritedByRefCtorInvalidatesLocalAllowance(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynAfterByRefCtor');
        new ChildByRefGreeterWrapper($mock);
        $mock->shouldReceive('dynAfterByRefCtor')->andReturn('x');
    }
}

final class StrictTestCaseAllowanceFixture extends TestCase
{
    private StrictGreeter&MockInterface $prop;

    private function makeSut(): StrictSut
    {
        return new StrictSut();
    }

    private function resetProp(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
    }

    public function frameworkAssertionsAndInstanceCallsPreserveAllowances(StrictSut $sut): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynPropAfterAssertions');
        $this->assertInstanceOf(StrictGreeter::class, $this->prop);
        self::assertSame($this->prop, $this->prop);
        $this->expectException(RuntimeException::class);
        $this->prop->shouldReceive('dynPropAfterAssertions')->andReturn('ok');

        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynLocalAfterSafeCalls');
        $this->assertInstanceOf(StrictGreeter::class, $mock);
        self::assertSame($mock, $mock);
        $sut->setGreeter($mock);
        $this->makeSut()->setGreeter($mock);
        new ChildGreeterWrapper($mock);
        $wrapper = new NoCtorWrapper();
        unset($wrapper);
        spl_object_id($mock);
        $mock->shouldReceive('dynLocalAfterSafeCalls')->andReturn('ok');
    }

    public function userHelperOnTestCaseStillInvalidatesPropertyAllowance(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynPropAfterTestCaseHelper');
        $this->resetProp();
        $this->prop->shouldReceive('dynPropAfterTestCaseHelper')->andReturn('x');
    }
}
