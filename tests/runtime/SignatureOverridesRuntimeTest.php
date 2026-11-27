<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\Exception as MockeryException;
use Mockery\HigherOrderMessage;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

interface CompatRuntimeGreeter
{
    public function greet(string $name): string;

    public function countGreetings(): int;
}

class CompatRuntimeGreeterImpl implements CompatRuntimeGreeter
{
    public function __construct(public readonly string $prefix = 'hi')
    {
    }

    public function greet(string $name): string
    {
        return $this->prefix . ':' . $name;
    }

    public function countGreetings(): int
    {
        return 1;
    }
}

final class SignatureOverridesRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Mockery::globalHelpers();
    }

    protected function tearDown(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
        Mockery::close();
        parent::tearDown();
    }

    public function testFluentBuildersReturnSameMockInstance(): void
    {
        $mock = Mockery::mock(CompatRuntimeGreeterImpl::class, ['hi']);

        self::assertSame($mock, $mock->makePartial());
        self::assertSame($mock, $mock->byDefault());
        self::assertSame($mock, $mock->shouldAllowMockingProtectedMethods());
        self::assertSame($mock, $mock->shouldAllowMockingMethod('dynamicMethod'));
        self::assertSame($mock, $mock->shouldDeferMissing());
        self::assertSame('hi:world', $mock->greet('world'));
    }

    public function testGlobalHelpersAndFactoriesAcceptMapsCtorArgsAndAliases(): void
    {
        $fromMockMap = mock(CompatRuntimeGreeter::class, ['greet' => 'from-map']);
        self::assertSame('from-map', $fromMockMap->greet('x'));

        $fromMockCtor = mock(CompatRuntimeGreeterImpl::class, ['ctor'])->makePartial();
        self::assertSame('ctor:x', $fromMockCtor->greet('x'));

        $fromSpy = spy(CompatRuntimeGreeterImpl::class, ['spy-ctor']);
        self::assertInstanceOf(CompatRuntimeGreeterImpl::class, $fromSpy);

        $fromNamed = namedMock('RuntimeNamedHelperAlias', CompatRuntimeGreeter::class);
        self::assertInstanceOf(CompatRuntimeGreeter::class, $fromNamed);
        self::assertInstanceOf(MockInterface::class, $fromNamed);

        $fromStaticNamed = Mockery::namedMock('RuntimeNamedStaticAlias', CompatRuntimeGreeter::class);
        self::assertInstanceOf(CompatRuntimeGreeter::class, $fromStaticNamed);
    }

    public function testShouldReceiveAcceptsAssociativeMapAndListFormFailsStrictValidation(): void
    {
        $mock = Mockery::mock(CompatRuntimeGreeter::class);
        $mock->shouldReceive(['greet' => 'mapped', 'countGreetings' => 7]);
        self::assertSame('mapped', $mock->greet('x'));
        self::assertSame(7, $mock->countGreetings());

        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);
        $strictMock = Mockery::mock(CompatRuntimeGreeter::class);
        $this->expectException(MockeryException::class);
        $strictMock->shouldReceive(['greet']);
    }

    public function testAllowsSecondArgumentIsIgnoredAtRuntime(): void
    {
        $mock = Mockery::mock(CompatRuntimeGreeter::class);
        $mock->allows('greet', 'countGreetings')->andReturn('ok');

        self::assertSame('ok', $mock->greet('a'));
        self::assertNull($mock->mockery_getExpectationsFor('countGreetings'));
    }

    public function testAllowsUnionArrayReturnsHigherOrderWhenEmptyAndMockWhenNonEmpty(): void
    {
        /** @var array{}|array{greet: string} $emptyBranch */
        $emptyBranch = [];
        $emptyMock = Mockery::mock(CompatRuntimeGreeter::class);
        $emptyResult = $emptyMock->allows($emptyBranch);
        self::assertInstanceOf(HigherOrderMessage::class, $emptyResult);
        $emptyResult->greet('a')->andReturn('from-higher-order');
        self::assertSame('from-higher-order', $emptyMock->greet('a'));

        /** @var array{}|array{greet: string} $nonEmptyBranch */
        $nonEmptyBranch = ['greet' => 'from-map'];
        $mapMock = Mockery::mock(CompatRuntimeGreeter::class);
        $mapResult = $mapMock->allows($nonEmptyBranch);
        self::assertSame($mapMock, $mapResult);
        self::assertSame('from-map', $mapMock->greet('a'));
    }
}
