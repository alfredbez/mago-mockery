<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

interface FirstContract
{
    public function first(): string;
}

interface SecondContract
{
    public function second(): string;
}

class PartialService
{
    public function __construct(private readonly string $prefix = 'default') {}

    public function primary(): string
    {
        return $this->prefix . ':primary';
    }

    public function secondary(): string
    {
        return $this->prefix . ':secondary';
    }
}

class ConcreteGreeter
{
    public function greet(string $name): string
    {
        return 'hello ' . $name;
    }

    public function farewell(string $name): string
    {
        return 'bye ' . $name;
    }
}

trait RuntimeLoggableTrait
{
    public function logFromTrait(string $msg): string
    {
        return 'trait:' . $this->formatTraitTag($msg);
    }

    abstract public function formatTraitTag(string $msg): string;
}

final class MockSpecsRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testAliasMockInterceptsStaticCalls(): void
    {
        $mock = Mockery::mock('alias:AlfredBez\\MagoMockery\\Runtime\\UnloadedAliasTarget');
        $mock->shouldReceive('ping')->once()->with('go')->andReturn('pong');

        self::assertInstanceOf(MockInterface::class, $mock);
        self::assertSame('pong', UnloadedAliasTarget::ping('go'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOverloadMockInterceptsInstantiation(): void
    {
        $mock = Mockery::mock('overload:AlfredBez\\MagoMockery\\Runtime\\UnloadedOverloadTarget');
        $mock->shouldReceive('compute')->once()->with(21)->andReturn(42);

        $instance = new UnloadedOverloadTarget();

        self::assertInstanceOf(MockInterface::class, $mock);
        self::assertSame(42, $instance->compute(21));
    }

    public function testWhitelistedPartialMockStubsOnlyListedMethods(): void
    {
        $partial = Mockery::mock(PartialService::class . '[primary]', ['ctor']);
        $partial->shouldReceive('primary')->once()->andReturn('stubbed');

        self::assertInstanceOf(PartialService::class, $partial);
        self::assertInstanceOf(MockInterface::class, $partial);
        self::assertSame('stubbed', $partial->primary());
        self::assertSame('ctor:secondary', $partial->secondary());
    }

    public function testNegatedPartialMockPreservesExcludedMethod(): void
    {
        $partial = Mockery::mock(PartialService::class . '[!secondary]', ['keep']);
        $partial->shouldReceive('primary')->once()->andReturn('mocked');

        self::assertSame('mocked', $partial->primary());
        self::assertSame('keep:secondary', $partial->secondary());
    }

    public function testConcreteObjectPartialMockDelegatesUnmockedCalls(): void
    {
        $real = new ConcreteGreeter();
        $partial = Mockery::mock($real);
        $partial->shouldReceive('greet')->once()->with('Ada')->andReturn('override');

        self::assertInstanceOf(ConcreteGreeter::class, $partial);
        self::assertInstanceOf(MockInterface::class, $partial);
        self::assertSame('override', $partial->greet('Ada'));
        self::assertSame('bye Ada', $partial->farewell('Ada'));
    }

    public function testMultiInterfaceWithConstructorArgsQuickDefsAndClosure(): void
    {
        $mock = Mockery::mock(
            PartialService::class,
            FirstContract::class,
            SecondContract::class,
            ['built'],
            ['first' => 'quick-first'],
            static function (MockInterface $configured): void {
                $configured->shouldReceive('second')->once()->andReturn('closure-second');
            },
        );
        $mock->makePartial();

        self::assertInstanceOf(PartialService::class, $mock);
        self::assertInstanceOf(FirstContract::class, $mock);
        self::assertInstanceOf(SecondContract::class, $mock);
        self::assertSame('built:primary', $mock->primary());
        self::assertSame('quick-first', $mock->first());
        self::assertSame('closure-second', $mock->second());
    }

    public function testCommaAndPipeSeparatedSpecs(): void
    {
        $comma = Mockery::mock(FirstContract::class . ', ' . SecondContract::class);
        $comma->shouldReceive('first')->once()->andReturn('one');
        $comma->shouldReceive('second')->once()->andReturn('two');

        self::assertInstanceOf(FirstContract::class, $comma);
        self::assertInstanceOf(SecondContract::class, $comma);
        self::assertSame('one', $comma->first());
        self::assertSame('two', $comma->second());

        $pipe = Mockery::mock(PartialService::class . '[primary]|' . FirstContract::class, ['pipe']);
        $pipe->shouldReceive('primary')->once()->andReturn('pipe-primary');
        $pipe->shouldReceive('first')->once()->andReturn('pipe-first');

        self::assertInstanceOf(PartialService::class, $pipe);
        self::assertInstanceOf(FirstContract::class, $pipe);
        self::assertSame('pipe-primary', $pipe->primary());
        self::assertSame('pipe:secondary', $pipe->secondary());
        self::assertSame('pipe-first', $pipe->first());
    }

    public function testTraitMockAndFetchMockAtRuntime(): void
    {
        $traitMock = Mockery::mock(RuntimeLoggableTrait::class);
        $traitMock->shouldReceive('formatTraitTag')->once()->with('event')->andReturn('mocked-tag');
        self::assertSame('trait:mocked-tag', $traitMock->logFromTrait('event'));

        $named = Mockery::namedMock('FetchedRuntimeGreeter', FirstContract::class);
        $named->shouldReceive('first')->once()->andReturn('from-fetched');
        $fetched = Mockery::fetchMock('FetchedRuntimeGreeter');
        self::assertSame($named, $fetched);
        self::assertInstanceOf(FirstContract::class, $fetched);
        self::assertSame('from-fetched', $fetched->first());
        self::assertNull(Mockery::fetchMock('UnregisteredMockName'));
    }
}
