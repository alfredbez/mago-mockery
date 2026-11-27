<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

final class SignatureOverridesAndUnions extends Support
{
    /**
     * @param array{}|array{greet: string} $unionShape
     * @param array{}|non-empty-array<string, string> $unionNonEmpty
     * @param array{greet?: string} $optionalShape
     * @param array{}|list{} $alwaysEmptyUnion
     */
    public function allowsUnionArraysAndFactories(
        array $unionShape,
        array $unionNonEmpty,
        array $optionalShape,
        array $alwaysEmptyUnion,
    ): void {
        $mock = Mockery::mock(Greeter::class);

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

        $fromOptionalShape = $mock->allows($optionalShape);
        if ($fromOptionalShape instanceof MockInterface) {
            $fromOptionalShape->greet('mapped');
        } else {
            $fromOptionalShape->greet('x')->andReturn('hi');
        }

        $mock->allows($alwaysEmptyUnion)->greet('x')->andReturn('hi');

        $globalMock = mock(Greeter::class, ['greet' => 'hi']);
        $globalMock->greet('global-map');

        $globalSpy = spy(Greeter::class, [1, 'two']);
        $globalSpy->greet('global-spy');

        $globalNamed = namedMock('GlobalHelperAlias', Greeter::class);
        $globalNamed->greet('global-named');

        $staticNamed = Mockery::namedMock('StaticFactoryAlias', Greeter::class);
        $staticNamed->greet('static-named');

        $anonStatic = Mockery::mock();
        $anonStatic->shouldReceive('anything');

        $anonGlobal = mock();
        $anonGlobal->shouldReceive('anything');

        $withClosure = Mockery::mock(Greeter::class, static function (MockInterface $configured): void {
            $configured->shouldReceive('greet')->andReturn('hi');
        });
        $withClosure->greet('closure-config');

        $mock->makePartial()
            ->byDefault()
            ->shouldAllowMockingProtectedMethods()
            ->shouldAllowMockingMethod('dynamicHelper')
            ->shouldDeferMissing()
            ->greet('fluent');
    }
}
