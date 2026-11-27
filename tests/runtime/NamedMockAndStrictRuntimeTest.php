<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\Exception as MockeryException;
use Mockery\Exception\BadMethodCallException;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

interface StrictRuntimeGreeter
{
    public function greet(string $name): string;
}

class StrictRuntimeOther
{
    public function otherOnly(): int
    {
        return 42;
    }
}

interface StrictRuntimeHolder
{
    public function getChildMock(): StrictRuntimeGreeter&MockInterface;
}

final class NamedMockAndStrictRuntimeTest extends TestCase
{
    private StrictRuntimeGreeter&MockInterface $prop;

    protected function tearDown(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(true);
        Mockery::close();
        parent::tearDown();
    }

    private function resetPropMock(): void
    {
        $this->prop = Mockery::mock(StrictRuntimeGreeter::class);
    }

    public function testConditionalNamedMockOverSetUpThrowsWhenConditionIsFalse(): void
    {
        Mockery::namedMock('RuntimeConditionalShadowAlias', StrictRuntimeGreeter::class);

        $cond = false;
        if ($cond) {
            Mockery::namedMock('RuntimeConditionalShadowAlias', StrictRuntimeOther::class);
        }

        $fetched = Mockery::fetchMock('RuntimeConditionalShadowAlias');
        self::assertInstanceOf(StrictRuntimeGreeter::class, $fetched);

        $this->expectException(BadMethodCallException::class);
        $fetched->otherOnly();
    }

    public function testStrictHelperResetInvalidatesPropertyAllowance(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $this->prop = Mockery::mock(StrictRuntimeGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterHelper');
        $this->resetPropMock();

        $this->expectException(MockeryException::class);
        $this->prop->shouldReceive('dynAfterHelper');
    }

    public function testStrictThisAliasInvalidatesPropertyAllowance(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $this->prop = Mockery::mock(StrictRuntimeGreeter::class);
        $this->prop->shouldAllowMockingMethod('dynAfterAlias');
        $self = $this;
        $self->prop = Mockery::mock(StrictRuntimeGreeter::class);

        $this->expectException(MockeryException::class);
        $this->prop->shouldReceive('dynAfterAlias');
    }

    public function testStrictNonFluentChainDoesNotCarryAllowance(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $child = Mockery::mock(StrictRuntimeGreeter::class);
        $holder = Mockery::mock(StrictRuntimeHolder::class);
        $holder->shouldReceive('getChildMock')->andReturn($child);
        $holder->shouldAllowMockingMethod('dynOnChild');

        $this->expectException(MockeryException::class);
        $holder->getChildMock()->shouldReceive('dynOnChild');
    }

    public function testStrictByRefReplacementInvalidatesLocalAllowance(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        $replace = static function (MockInterface &$target): void {
            $target = Mockery::mock(StrictRuntimeGreeter::class);
        };

        $mock = Mockery::mock(StrictRuntimeGreeter::class);
        $mock->shouldAllowMockingMethod('dynAfterByRef');
        $replace($mock);

        $this->expectException(MockeryException::class);
        $mock->shouldReceive('dynAfterByRef');
    }

    public function testStrictLiteralUnionThrowsOnBothUnknownBranches(): void
    {
        Mockery::getConfiguration()->allowMockingNonExistentMethods(false);

        foreach ([true, false] as $cond) {
            $mock = Mockery::mock(StrictRuntimeGreeter::class);
            $threw = false;
            try {
                $mock->shouldReceive($cond ? 'missingA' : 'missingB');
            } catch (MockeryException) {
                $threw = true;
            }
            self::assertTrue($threw, 'Both unknown literal branches must throw Mockery\\Exception.');
        }
    }
}
