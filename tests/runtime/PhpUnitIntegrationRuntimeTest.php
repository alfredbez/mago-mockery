<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

interface TraitNotifier
{
    public function notify(string $message): bool;
}

final class PhpUnitIntegrationRuntimeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testTraitVerifiesMockeryExpectationsWithoutManualClose(): void
    {
        $notifier = Mockery::mock(TraitNotifier::class);
        $notifier->shouldReceive('notify')->once()->with('ping')->andReturn(true);

        self::assertTrue($notifier->notify('ping'));
        self::assertTrue($this->mockeryOpen);
    }
}
