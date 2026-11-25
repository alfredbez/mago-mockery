<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * HigherOrderMessage forwards calls to the underlying mock method.
 *
 * When produced by NoArgumentFactoryProvider, the receiver carries the forwarded
 * return type as parameter 0 (`CompositeExpectation<Mock>`, `VerificationDirector`,
 * or `void`) and the mocked type as parameter 1.
 *
 * @internal
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

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        if (!$this->shouldForward($context->invocation)) {
            return null;
        }

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

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $forwarded = self::extractForwardedReturnType($context->invocation->receiverType);
        if ($forwarded === null || !$this->shouldForward($context->invocation)) {
            return null;
        }

        return $forwarded;
    }

    public static function extractMockType(?Type $receiverType): ?Type
    {
        if ($receiverType === null) {
            return null;
        }

        foreach ($receiverType->atomicTypes as $atomic) {
            if (
                $atomic instanceof NamedObjectType
                && isset(self::HIGHER_ORDER_CLASSES[strtolower($atomic->name)])
                && $atomic->parameters !== null
            ) {
                if (isset($atomic->parameters[1])) {
                    return $atomic->parameters[1];
                }

                if (isset($atomic->parameters[0])) {
                    return CompositeExpectationProvider::extractMockType($atomic->parameters[0]);
                }
            }
        }

        return null;
    }

    private function shouldForward(Invocation $invocation): bool
    {
        if (str_starts_with($invocation->name, '__')) {
            return false;
        }

        return self::extractMockType($invocation->receiverType) !== null
            || self::extractForwardedReturnType($invocation->receiverType) !== null;
    }

    public static function extractForwardedReturnType(?Type $receiverType): ?Type
    {
        if ($receiverType === null) {
            return null;
        }

        foreach ($receiverType->atomicTypes as $atomic) {
            if (
                $atomic instanceof NamedObjectType
                && isset(self::HIGHER_ORDER_CLASSES[strtolower($atomic->name)])
                && $atomic->parameters !== null
                && isset($atomic->parameters[0])
            ) {
                return $atomic->parameters[0];
            }
        }

        return null;
    }
}
