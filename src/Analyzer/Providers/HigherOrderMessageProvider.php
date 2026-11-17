<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * HigherOrderMessage forwards every call to the expectation of the named method.
 *
 * `$mock->shouldReceive()->greet('x')->andReturn('hi')` never calls a declared
 * method on HigherOrderMessage, so Mago reports non-existent-method or
 * non-documented-method. Declaring the call shape and its return type teaches
 * the analyzer the forward.
 *
 * Only the no-argument form of shouldReceive()/expects() reaches this class at
 * runtime; the provider for that split is NoArgumentFactoryProvider.
 */
final class HigherOrderMessageProvider implements MethodReturnTypeProvider, CallableSignatureProvider
{
    public const EXPECTATION = 'Mockery\\Expectation';

    public function getTargets(): array
    {
        return [
            MethodTarget::allMethods('Mockery\\HigherOrderMessage'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): EffectiveCallableSignature
    {
        return new EffectiveCallableSignature(
            parameters: [
                new CallableParameter(name: '$arguments', type: Type::mixed(), variadic: true),
            ],
            allowsNamedArguments: true,
            displayName: 'Mockery\\HigherOrderMessage::' . $context->invocation->name,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): Type
    {
        return Type::namedObject(self::EXPECTATION);
    }
}
