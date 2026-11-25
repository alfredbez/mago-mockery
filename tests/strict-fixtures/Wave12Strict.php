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
}
