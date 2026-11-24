<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Error;
use Mockery;
use Mockery\Exception as MockeryException;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

interface StrictRuntimeService
{
    public function ping(string $value): string;
}

interface ExtendedRuntimeService extends StrictRuntimeService
{
    public function pong(): string;
}

class ConcreteRuntimeService implements StrictRuntimeService
{
    public function ping(string $value): string
    {
        return 'real:' . $value;
    }
}

trait RuntimeConstantTrait
{
    public const CODE = 'trait-const-ok';

    public function describe(): string
    {
        return self::CODE;
    }
}

final class Wave9ContractsRuntimeTest extends TestCase
{
    private bool $previousAllowNonExistent = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousAllowNonExistent = Mockery::getConfiguration()->mockingNonExistentMethodsAllowed();
    }

    protected function tearDown(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods($this->previousAllowNonExistent);
        Mockery::close();
        parent::tearDown();
    }

    public function testAliasAllowancePermitsDynamicMethodInStrictRuntime(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $mock = Mockery::mock(StrictRuntimeService::class);
        $alias = $mock;
        $alias->shouldAllowMockingMethod('aliasedDynamic');
        $mock->shouldReceive('aliasedDynamic')->once()->andReturn('alias-ok');

        self::assertSame('alias-ok', $mock->aliasedDynamic());
    }

    public function testReassignedMockRejectsPreviousAllowanceInStrictRuntime(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $mock = Mockery::mock(StrictRuntimeService::class);
        $mock->shouldAllowMockingMethod('dynamicAfterReassignment');
        $mock = Mockery::mock(StrictRuntimeService::class);

        $this->expectException(MockeryException::class);
        $mock->shouldReceive('dynamicAfterReassignment')->andReturn('x');
    }

    public function testConditionalAllowanceThrowsOnFalseBranchInStrictRuntime(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $mock = Mockery::mock(StrictRuntimeService::class);
        $flag = false;
        if ($flag) {
            $mock->shouldAllowMockingMethod('dynamicAfterConditional');
        }

        $this->expectException(MockeryException::class);
        $mock->shouldReceive('dynamicAfterConditional')->andReturn('x');
    }

    public function testConditionalAllowanceReturnsOnTrueBranchInStrictRuntime(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $mock = Mockery::mock(StrictRuntimeService::class);
        $flag = true;
        if ($flag) {
            $mock->shouldAllowMockingMethod('dynamicAfterConditional');
        }

        $mock->shouldReceive('dynamicAfterConditional')->once()->andReturn('x');
        self::assertSame('x', $mock->dynamicAfterConditional());
    }

    public function testHigherOrderUnknownMethodThrowsInStrictRuntimeWhileKnownAndInheritedWork(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $known = Mockery::mock(ExtendedRuntimeService::class);
        $known->shouldReceive()->ping('ok')->once()->andReturn('pong-ok');
        self::assertSame('pong-ok', $known->ping('ok'));

        $bad = Mockery::mock(StrictRuntimeService::class);
        $this->expectException(MockeryException::class);
        $bad->shouldReceive()->missingHigherOrder('bad');
    }

    public function testClassStringFactoryAndContainerAndParenthesizedTraitConstantsWorkAtRuntime(): void
    {
        /** @var class-string<ConcreteRuntimeService> $className */
        $className = ConcreteRuntimeService::class;
        $mock = Mockery::mock($className);
        $mock->shouldReceive('ping')->with('hi')->once()->andReturn('mocked:hi');

        self::assertInstanceOf(ConcreteRuntimeService::class, $mock);
        self::assertInstanceOf(MockInterface::class, $mock);
        self::assertSame('mocked:hi', $mock->ping('hi'));

        $container = Mockery::getContainer();
        $containerTrait = $container->mock(RuntimeConstantTrait::class);
        self::assertSame('trait-const-ok', $containerTrait::CODE);
        self::assertSame('trait-const-ok', (Mockery::mock(RuntimeConstantTrait::class))::CODE);
    }

    public function testStaleTraitClassStringConstantAccessFatalsAtRuntime(): void
    {
        $traitMock = Mockery::mock(RuntimeConstantTrait::class);
        self::assertSame('trait-const-ok', $traitMock::CODE);

        $traitMock = RuntimeConstantTrait::class;
        $this->expectException(Error::class);
        $this->expectExceptionMessage('Cannot access trait constant');
        /** @var mixed $_unused */
        $_unused = $traitMock::CODE;
    }
}
