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
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * HigherOrderMessage forwards every call to the underlying mock method.
 *
 * When produced by NoArgumentFactoryProvider, the receiver carries the forwarded
 * return type as a type parameter (`CompositeExpectation<Mock>`, `VerificationDirector`,
 * or `void`). Unparameterized instances fall back to `Mockery\Expectation`.
 */
final class HigherOrderMessageProvider implements MethodReturnTypeProvider, CallableSignatureProvider
{
    public const EXPECTATION = 'Mockery\\Expectation';

    private const HIGHER_ORDER_CLASSES = [
        'mockery\\higherordermessage' => true,
        'mockery\\expectshigherordermessage' => true,
    ];

    public function getTargets(): array
    {
        return [
            MethodTarget::allMethods('Mockery\\HigherOrderMessage'),
            MethodTarget::allMethods('Mockery\\ExpectsHigherOrderMessage'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): EffectiveCallableSignature
    {
        return new EffectiveCallableSignature(
            parameters: [
                new CallableParameter(name: '$arguments', type: Type::mixed(), variadic: true),
            ],
            allowsNamedArguments: true,
            displayName: ($context->invocation->declaringClass ?? 'Mockery\\HigherOrderMessage')
                . '::'
                . $context->invocation->name,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): Type
    {
        $receiver = $context->invocation->receiverType;
        if ($receiver !== null) {
            foreach ($receiver->atomicTypes as $atomic) {
                if (
                    $atomic instanceof NamedObjectType
                    && isset(self::HIGHER_ORDER_CLASSES[strtolower($atomic->name)])
                    && $atomic->parameters !== null
                    && isset($atomic->parameters[0])
                ) {
                    return $atomic->parameters[0];
                }
            }
        }

        return Type::namedObject(self::EXPECTATION);
    }
}
