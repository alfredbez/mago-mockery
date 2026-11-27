<?php

declare(strict_types=1);

namespace MockeryCompat1612;

use Mockery;

final class Mockery1612Negative
{
    public function invalidShapesStayRejected(): void
    {
        $mock = Mockery::mock(CompatGreeter::class);

        $mock->allows('greet', 'countGreetings');
        $mock->shouldReceive(['greet']);
        $mock->shouldNotReceive(['greet']);

        $mock->makePartial()->missingAfterPartial();
        $mock->byDefault()->missingAfterByDefault();
        $mock->shouldAllowMockingProtectedMethods()->missingAfterProtected();
        $mock->shouldAllowMockingMethod('dynamicMethod')->missingAfterAllowMethod();
        $mock->shouldDeferMissing()->missingAfterDefer();

        Mockery::mock(123);
        Mockery::spy(false);
        Mockery::namedMock();
        namedMock();
    }
}
