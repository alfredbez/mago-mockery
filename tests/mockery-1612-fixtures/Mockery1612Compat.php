<?php

declare(strict_types=1);

namespace MockeryCompat1612;

use Mockery;
use Mockery\MockInterface;

interface CompatGreeter
{
    public function greet(string $name): string;

    public function countGreetings(): int;
}

final class Mockery1612Compat
{
    /**
     * @param array{}|array{greet: string} $unionShape
     * @param array{}|non-empty-array<string, string> $unionNonEmpty
     */
    public function validCompatShapes(array $unionShape, array $unionNonEmpty): void
    {
        $fromGlobalMockMap = mock(CompatGreeter::class, ['greet' => 'hi']);
        $fromGlobalMockMap->greet('world');

        $fromGlobalMockCtor = mock(CompatGreeter::class, [1, 'two']);
        $fromGlobalMockCtor->greet('world');

        $fromGlobalSpy = spy(CompatGreeter::class, [1, 'two']);
        $fromGlobalSpy->greet('world');

        $fromGlobalNamed = namedMock('GlobalCompatAlias', CompatGreeter::class);
        $fromGlobalNamed->greet('world');

        $fromStaticMockMap = Mockery::mock(CompatGreeter::class, ['greet' => 'hi']);
        $fromStaticMockMap->greet('world');

        $fromStaticMockCtor = Mockery::mock(CompatGreeter::class, [1, 'two']);
        $fromStaticMockCtor->greet('world');

        $fromStaticSpy = Mockery::spy(CompatGreeter::class, [1, 'two']);
        $fromStaticSpy->greet('world');

        $fromStaticNamed = Mockery::namedMock('StaticCompatAlias', CompatGreeter::class);
        $fromStaticNamed->greet('world');

        $fromInstanceMock = Mockery::instanceMock(CompatGreeter::class, [1, 'two']);
        $fromInstanceMock->greet('world');

        $fromContainer = Mockery::getContainer()->mock(CompatGreeter::class, ['greet' => 'hi']);
        $fromContainer->greet('world');

        $mock = Mockery::mock(CompatGreeter::class);
        $mock->shouldReceive(['greet' => 'hi', 'countGreetings' => 1])->getMock()->greet('world');
        $mock->shouldReceive('greet', 'countGreetings')->andReturn('hi');

        $mock->makePartial()->greet('partial');
        $mock->byDefault()->greet('default');
        $mock->shouldAllowMockingProtectedMethods()->greet('protected');
        $mock->shouldAllowMockingMethod('dynamicMethod')->greet('allowed');
        $mock->shouldDeferMissing()->greet('deferred');
        $mock->shouldIgnoreMissing(null, true)->asUndefined()->greet('ignored');

        $fromUnionShape = $mock->allows($unionShape);
        if ($fromUnionShape instanceof MockInterface) {
            $fromUnionShape->greet('mapped');
        } else {
            $fromUnionShape->greet('x')->andReturn('hi');
        }

        $fromUnionNonEmpty = $mock->allows($unionNonEmpty);
        if ($fromUnionNonEmpty instanceof MockInterface) {
            $fromUnionNonEmpty->greet('mapped');
        } else {
            $fromUnionNonEmpty->greet('x')->andReturn('hi');
        }
    }
}
