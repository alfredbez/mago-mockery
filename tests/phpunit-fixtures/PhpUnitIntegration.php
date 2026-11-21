<?php

declare(strict_types=1);

namespace MockeryFixture\PhpUnit;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

interface Mailer
{
    public function send(string $recipient): bool;
}

final class MockeryTestCaseFixture extends MockeryTestCase
{
    private Mailer&MockInterface $mailer;

    protected function mockeryTestSetUp(): void
    {
        $this->mailer = Mockery::mock(Mailer::class);
    }

    protected function mockeryTestTearDown(): void
    {
        $this->mailer->shouldHaveReceived('send');
    }

    public function testSendsMail(): void
    {
        $this->mailer->shouldReceive('send')->once()->with('a@example.com')->andReturn(true);
        self::assertTrue($this->mailer->send('a@example.com'));
    }
}

final class MockeryTraitFixture extends TestCase
{
    use MockeryPHPUnitIntegration;

    private Mailer&MockInterface $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = Mockery::mock(Mailer::class);
    }

    public function testSendsMailWithTrait(): void
    {
        $this->mailer->shouldReceive('send')->once()->with('b@example.com')->andReturn(true);
        self::assertTrue($this->mailer->send('b@example.com'));
    }
}

final class UninitializedPropertyNegativeFixture extends MockeryTestCase
{
    private Mailer&MockInterface $initialized;
    private string $neverInitialized;

    protected function mockeryTestSetUp(): void
    {
        $this->initialized = Mockery::mock(Mailer::class);
    }

    public function testUsesBothProperties(): void
    {
        $this->initialized->shouldReceive('send')->andReturn(true);
        self::assertTrue($this->initialized->send($this->neverInitialized));
    }
}

final class PlainPhpUnitControlFixture extends TestCase
{
    private string $initializedInSetUpOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initializedInSetUpOnly = 'plain';
    }

    public function testControl(): void
    {
        self::assertSame('plain', $this->initializedInSetUpOnly);
    }
}
