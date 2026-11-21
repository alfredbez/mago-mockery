<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;

interface LifecycleNotifier
{
    public function notify(string $message): bool;
}

final class MockeryTestCaseLifecycleRuntimeTest extends MockeryTestCase
{
    private LifecycleNotifier&MockInterface $notifier;
    private bool $tearDownRan = false;

    protected function mockeryTestSetUp(): void
    {
        $this->notifier = Mockery::mock(LifecycleNotifier::class);
    }

    protected function mockeryTestTearDown(): void
    {
        $this->tearDownRan = true;
    }

    public function testMockeryTestSetUpInitializesTypedMockProperty(): void
    {
        $this->notifier->shouldReceive('notify')->once()->with('hello')->andReturn(true);

        self::assertTrue($this->notifier->notify('hello'));
        self::assertFalse($this->tearDownRan);
    }
}
