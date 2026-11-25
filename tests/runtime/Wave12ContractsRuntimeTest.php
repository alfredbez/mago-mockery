<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use Mockery;
use Mockery\HigherOrderMessage;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use stdClass;

interface Wave12Recorder
{
    public function record(string $name, mixed $payload = null): mixed;
}

class Wave12Box
{
    public string $label = '';

    public function touch(): void
    {
    }
}

final class Wave12ContractsRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testShouldIgnoreMissingAcceptsRecursiveFlag(): void
    {
        $mock = Mockery::mock(Wave12Recorder::class);
        $result = $mock->shouldIgnoreMissing(null, true);

        self::assertSame($mock, $result);
        self::assertNull($mock->record('missing'));
    }

    public function testAsUndefinedReturnsTheMock(): void
    {
        $mock = Mockery::mock(Wave12Recorder::class);
        $result = $mock->asUndefined();

        self::assertSame($mock, $result);
    }

    public function testExpectationSetForwardsVariadicValues(): void
    {
        $mock = Mockery::mock(Wave12Box::class);
        $mock->shouldReceive('touch')->set('label', 'first', 'second');

        $mock->touch();
        self::assertSame('first', $mock->label);
        $mock->touch();
        self::assertSame('second', $mock->label);
    }

    public function testAllowsNullIsHigherOrder(): void
    {
        $mock = Mockery::mock(Wave12Recorder::class);
        $fromNull = $mock->allows(null);
        self::assertInstanceOf(HigherOrderMessage::class, $fromNull);
        $fromNull->record('x')->andReturn('from-null');
        self::assertSame('from-null', $mock->record('x'));
    }

    public function testAllowsEmptyAndNonEmptyMaps(): void
    {
        $emptyMock = Mockery::mock(Wave12Recorder::class);
        $fromEmpty = $emptyMock->allows([]);
        self::assertInstanceOf(HigherOrderMessage::class, $fromEmpty);

        $mapMock = Mockery::mock(Wave12Recorder::class);
        $map = ['record' => 'mapped'];
        $fromMap = $mapMock->allows($map);
        self::assertSame($mapMock, $fromMap);
        self::assertSame('mapped', $mapMock->record('any'));
    }

    public function testAllowsPossiblyEmptyMapTakesBothRuntimeBranches(): void
    {
        $empty = [];
        $emptyMock = Mockery::mock(Wave12Recorder::class);
        $fromEmpty = $emptyMock->allows($empty);
        self::assertInstanceOf(HigherOrderMessage::class, $fromEmpty);

        $nonEmpty = ['record' => 'present'];
        $nonEmptyMock = Mockery::mock(Wave12Recorder::class);
        $fromNonEmpty = $nonEmptyMock->allows($nonEmpty);
        self::assertInstanceOf(MockInterface::class, $fromNonEmpty);
        self::assertSame($nonEmptyMock, $fromNonEmpty);
        self::assertSame('present', $nonEmptyMock->record('any'));
    }

    public function testFetchMockAcceptsPlainStringAlias(): void
    {
        $aliasName = 'Wave12RuntimeAlias';
        Mockery::namedMock($aliasName, stdClass::class);
        $fetched = Mockery::fetchMock($aliasName);

        self::assertInstanceOf(MockInterface::class, $fetched);
        self::assertSame($fetched, Mockery::fetchMock($aliasName));
    }
}
