<?php

declare(strict_types=1);

namespace MockeryFixture\Strict;

use Mockery;
use Mockery\MockInterface;

final class Wave12StrictFixture
{
    private StrictGreeter&MockInterface $prop;

    public function higherOrderAllowances(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dyn');
        $mock->shouldNotReceive()->dyn();
        $mock->allows([])->dyn();
        $mock->expects(null)->dyn();
        $mock->shouldHaveReceived(null)->dyn();
        $mock->shouldNotHaveReceived(null)->dyn();
    }

    public function propertyAllowance(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dyn');
        $this->prop->shouldReceive()->dyn()->andReturn('x');
        $this->prop->shouldReceive('dyn')->andReturn('x');
    }

    public function unknownHigherOrderEmitsOnce(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldReceive()->missing()->andReturn('x');
    }

    public function propertyAllowanceInvalidatedOnReassignment(): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterReassign');
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldReceive('dynAfterReassign')->andReturn('x');
    }

    public function propertyAllowanceNotDominatedInBranch(bool $flag): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        if ($flag) {
            $this->prop->shouldAllowMockingMethod('dynConditional');
        }
        $this->prop->shouldReceive('dynConditional')->andReturn('x');
    }

    public function propertyAllowanceInvalidatedOnUnset(bool $flag): void
    {
        $this->prop = Mockery::mock(StrictGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterUnset');
        if ($flag) {
            unset($this->prop);
        }
        $this->prop->shouldReceive('dynAfterUnset')->andReturn('x');
    }
}
