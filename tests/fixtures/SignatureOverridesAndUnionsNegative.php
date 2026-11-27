<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;

final class SignatureOverridesAndUnionsNegative extends Support
{
    public function invalidExpectationArgumentShapes(): void
    {
        $mock = Mockery::mock(Greeter::class);
        $mock->allows('greet', 'countGreetings');
        $mock->shouldReceive(['greet']);
        $mock->shouldNotReceive(['greet']);
        $mock->shouldReceive('greet')->shouldReceive(['countGreetings']);
        $mock->shouldReceive('greet')->shouldNotReceive(['countGreetings']);
    }

    public function invalidFactoryArguments(): void
    {
        Mockery::mock(123);
        Mockery::spy(false);
        Mockery::getContainer()->mock(null);
        mock(123);
        spy(false);
        Mockery::namedMock();
        namedMock();
    }
}
