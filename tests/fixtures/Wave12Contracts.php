<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

final class Wave12Contracts extends Support
{
    private Greeter&MockInterface $prop;

    /**
     * @param class-string<GenericBox<string>> $boxClass
     * @param class-string<GenericBox<int>> $intBoxClass
     * @param array<string, string> $maybeEmptyMap
     * @param non-empty-array<string, string> $nonEmptyMap
     */
    public function genericMocksAndSignatureShapes(
        string $boxClass,
        string $intBoxClass,
        array $maybeEmptyMap,
        array $nonEmptyMap,
        string $aliasName,
    ): void {
        $fromObject = Mockery::mock(new GenericBox('hello'));
        $fromObjectValue = $fromObject->get();
        $fromObjectValue . 'x';

        $fromClassString = Mockery::mock($boxClass);
        $fromClassStringValue = $fromClassString->get();
        $fromClassStringValue . 'y';

        $fromIntClassString = Mockery::mock($intBoxClass);
        $fromIntClassStringValue = $fromIntClassString->get();
        $fromIntClassStringValue + 1;

        $idBox = Mockery::mock(new GenericBox(['id' => 1]));
        $nameBox = Mockery::mock(new GenericBox(['name' => 'alice']));
        $this->takesId($idBox->get());
        $this->takesName($nameBox->get());

        $mock = Mockery::mock(Greeter::class);
        $mock->shouldIgnoreMissing(null, true);
        $undefined = $mock->asUndefined();
        $undefined->greet('ok');

        $mock->shouldReceive('greet')->set('label', 'a', 'b')->andReturn('hi');
        $mock->allows(null)->greet('x')->andReturn('hi');

        $fromMaybeEmpty = $mock->allows($maybeEmptyMap);
        if ($fromMaybeEmpty instanceof MockInterface) {
            $fromMaybeEmpty->greet('mapped');
        } else {
            $fromMaybeEmpty->greet('x')->andReturn('hi');
        }

        $fromNonEmpty = $mock->allows($nonEmptyMap);
        $fromNonEmpty->greet('mapped');

        $fetched = Mockery::fetchMock($aliasName);
        if ($fetched !== null) {
            $fetched->shouldReceive('anything');
        }

        $namedFetch = Mockery::fetchMock(name: $aliasName);
        if ($namedFetch !== null) {
            $namedFetch->shouldReceive('anything');
        }

        $container = Mockery::getContainer();
        $containerFetched = $container->fetchMock($aliasName);
        if ($containerFetched !== null) {
            $containerFetched->shouldReceive('anything');
        }
    }

    /** @param array{id: int} $row */
    private function takesId(array $row): int
    {
        return $row['id'];
    }

    /** @param array{name: string} $row */
    private function takesName(array $row): string
    {
        return $row['name'];
    }

    public function sameMethodEarlierAlias(): void
    {
        Mockery::namedMock('SameMethodEarlierAlias', Greeter::class);
        $fetched = Mockery::fetchMock('SameMethodEarlierAlias');
        if ($fetched !== null) {
            $fetched->greet('same-method');
        }
    }

    public function higherOrderAllowances(): void
    {
        $mock = Mockery::mock(Greeter::class);
        $mock->shouldAllowMockingMethod('dyn');
        $mock->shouldNotReceive()->dyn();
        $mock->allows([])->dyn();
        $mock->expects(null)->dyn();
        $mock->shouldHaveReceived(null)->dyn();
        $mock->shouldNotHaveReceived(null)->dyn();
    }

    public function propertyAllowance(): void
    {
        $this->prop = Mockery::mock(Greeter::class);
        $this->prop->shouldAllowMockingMethod('dyn');
        $this->prop->shouldReceive()->dyn()->andReturn('x');
        $this->prop->shouldReceive('dyn')->andReturn('x');
    }

    public function setUp(): void
    {
        Mockery::namedMock('Wave12SetupAlias', Greeter::class);
    }

    #[Before]
    public function namedMockBeforeHook(): void
    {
        Mockery::namedMock('Wave12BeforeAlias', Greeter::class);
    }

    public function startMockery(): void
    {
        Mockery::namedMock('Wave12StartAlias', Greeter::class);
    }

    public function mockeryTestSetUp(): void
    {
        Mockery::namedMock('Wave12MockeryTestSetUpAlias', Greeter::class);
    }

    public function usesLifecycleAliases(): void
    {
        $fromSetUp = Mockery::fetchMock('Wave12SetupAlias');
        if ($fromSetUp !== null) {
            $fromSetUp->greet('from-setup');
            $this->acceptGreeter($fromSetUp);
        }

        $fromBefore = Mockery::fetchMock('Wave12BeforeAlias');
        if ($fromBefore !== null) {
            $fromBefore->greet('from-before');
        }

        $fromStart = Mockery::fetchMock('Wave12StartAlias');
        if ($fromStart !== null) {
            $fromStart->greet('from-start');
        }

        $fromMockerySetUp = Mockery::fetchMock('Wave12MockeryTestSetUpAlias');
        if ($fromMockerySetUp !== null) {
            $fromMockerySetUp->greet('from-mockery-setup');
        }
    }
}
