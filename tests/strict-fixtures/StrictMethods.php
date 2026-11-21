<?php

declare(strict_types=1);

namespace MockeryFixture\Strict;

use Mockery;

interface StrictGreeter
{
    public function greet(string $name): string;
}

final class MagicCaller
{
    /**
     * @param list<mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return [$name, $arguments];
    }
}

/**
 * @method string virtualGreet(string $name)
 */
class PseudoMethodTarget
{
    public function realMethod(): void {}
}

final class InheritedPseudoTarget extends PseudoMethodTarget {}

trait TraitWithMagic
{
    /** @param list<mixed> $arguments */
    public function __call(string $name, array $arguments): mixed
    {
        return null;
    }
}

final class TraitMagicTarget
{
    use TraitWithMagic;
}

class MixinProvider
{
    public function fromMixin(): void {}
}

/** @mixin MixinProvider */
class MixinParent {}

final class InheritedMixinTarget extends MixinParent {}

final class StrictMethodsFixture
{
    public function validCalls(string $dynamicName): void
    {
        $greeter = Mockery::mock(StrictGreeter::class);
        $greeter->shouldReceive('greet')->andReturn('hi');
        $greeter->shouldReceive('greet->nested')->andReturn('hi');
        $greeter->shouldNotReceive('greet');
        $greeter->allows('greet')->andReturn('hi');
        $greeter->allows(['greet' => 'hi']);
        $greeter->expects('greet')->andReturn('hi');
        $greeter->shouldHaveReceived('greet', ['x' => 1]);
        $greeter->shouldNotHaveReceived('greet', ['y' => 2]);
        $greeter->shouldReceive($dynamicName);
        $greeter->allows($dynamicName);

        $magic = Mockery::mock(MagicCaller::class);
        $magic->shouldReceive('dynamicMagicMethod')->andReturn(1);

        $traitMagic = Mockery::mock(TraitMagicTarget::class);
        $traitMagic->shouldReceive('traitMagicMethod')->andReturn(1);

        $pseudo = Mockery::mock(PseudoMethodTarget::class);
        $pseudo->shouldReceive('virtualGreet')->andReturn('ok');

        $inheritedPseudo = Mockery::mock(InheritedPseudoTarget::class);
        $inheritedPseudo->shouldReceive('virtualGreet')->andReturn('ok');

        $inheritedMixin = Mockery::mock(InheritedMixinTarget::class);
        $inheritedMixin->shouldReceive('fromMixin');

        $stdClassIntersection = Mockery::mock('\\stdClass, ' . StrictGreeter::class);
        $stdClassIntersection->shouldReceive('dynamicStub')->andReturn('ok');

        $allowedChained = Mockery::mock(StrictGreeter::class);
        $allowedChained->shouldAllowMockingMethod('allowedChained')->shouldReceive('allowedChained');

        $allowedSeparate = Mockery::mock(StrictGreeter::class);
        $allowedSeparate->shouldAllowMockingMethod('allowedSeparate');
        $nested = static function (): void {};
        $nested();
        $allowedSeparate->shouldReceive('allowedSeparate');

        $anonymous = Mockery::mock();
        $anonymous->shouldReceive('anythingAtAll')->andReturn(true);
    }

    public function invalidCalls(): void
    {
        $greeter = Mockery::mock(StrictGreeter::class);
        $otherGreeter = Mockery::mock(StrictGreeter::class);
        $greeter->shouldReceive('doesNotExist');
        $greeter->shouldReceive('greet')->shouldReceive('missingChained');
        $greeter->shouldNotReceive('missingNotReceive');
        $greeter->allows('missingAllows');
        $greeter->allows(['missingMapKey' => 'v']);
        $greeter->expects('missingExpects');
        $greeter->shouldHaveReceived('missingHaveReceived');
        $greeter->shouldNotHaveReceived('missingNotHaveReceived');
        $greeter->shouldReceive('missingDemeter->child');

        $greeter->shouldAllowMockingMethod('onlyThisOne');
        $greeter->shouldReceive('stillMissingAfterAllow');
        $greeter->shouldReceive('allowedSeparate');

        $otherGreeter->shouldAllowMockingMethod('onlyOnOtherMock');
        $greeter->shouldReceive('onlyOnOtherMock');

        // $greeter->shouldAllowMockingMethod('commentedOut');
        $greeter->shouldReceive('commentedOut');
    }
}
