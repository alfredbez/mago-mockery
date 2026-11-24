<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

interface SubGreeter extends Greeter
{
    public function farewell(string $name): string;
}

final class Wave9FactoriesAndTraits extends Support
{
    /**
     * @param class-string<Greeter> $greeterClass
     * @param class-string $anyClass
     */
    public function classStringFactories(string $greeterClass, string $anyClass, string $plainString, mixed $mixedValue): void
    {
        $literal = Mockery::mock(Greeter::class);
        $literal->greet('literal-control');

        $typed = Mockery::mock($greeterClass);
        $typed->greet('class-string-ok');
        $this->acceptGreeter($typed);

        $bare = Mockery::mock($anyClass);
        $bare->shouldReceive('anything');

        $fromString = Mockery::mock($plainString);
        $fromString->shouldReceive('anything');

        $fromMixed = Mockery::mock($mixedValue);
        $fromMixed->shouldReceive('anything');
    }

    public function sameFileNamedMockAndFetchMock(): void
    {
        $named = Mockery::namedMock('Wave9SameFileAlias', Greeter::class);
        $named->greet('direct');

        $fetched = Mockery::fetchMock('Wave9SameFileAlias');
        if ($fetched !== null) {
            $fetched->greet('fetched-same-file');
            $this->acceptGreeter($fetched);
        }

        $cross = Mockery::namedMock('Wave9CrossFileAlias', Greeter::class);
        $cross->greet('defined-here-only');
    }

    public function higherOrderKnownAndInheritedControls(): void
    {
        $greeter = Mockery::mock(Greeter::class);
        $greeter->shouldReceive()->greet('ok')->andReturn('hi');

        $sub = Mockery::mock(SubGreeter::class);
        $sub->shouldReceive()->greet('parent-interface-ok')->andReturn('hi');
        $sub->shouldReceive()->farewell('child-ok')->andReturn('bye');
    }

    public function traitMockConstantShapes(): void
    {
        $direct = Mockery::mock(LoggableTrait::class);
        $directTag = $direct::TAG;

        $container = Mockery::getContainer();
        $fromContainer = $container->mock(LoggableTrait::class);
        $containerTag = $fromContainer::TAG;

        $parenthesizedTag = (Mockery::mock(LoggableTrait::class))::TAG;
        $parenthesizedHelperTag = (mock(LoggableTrait::class))::TAG;
        $parenthesizedContainerTag = ($container->mock(LoggableTrait::class))::TAG;

        unset($directTag, $containerTag, $parenthesizedTag, $parenthesizedHelperTag, $parenthesizedContainerTag);
    }

    public function broadFactoryControl(): MockInterface
    {
        return Mockery::mock(Greeter::class);
    }
}
