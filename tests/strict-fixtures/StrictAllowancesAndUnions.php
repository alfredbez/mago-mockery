<?php

declare(strict_types=1);

namespace MockeryFixture\Strict;

use Mockery;
use Mockery\MockInterface;

interface ChildMockHolder
{
    public function getChildMock(): StrictGreeter&MockInterface;
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
    }
}
