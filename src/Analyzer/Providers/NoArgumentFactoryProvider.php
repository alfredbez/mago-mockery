<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

/**
 * Factory and verification methods called in higher-order form return a HigherOrderMessage.
 *
 * `shouldReceive()`, `shouldNotReceive()`, `allows()` / `allows([])`, `expects()` / `expects(null)`,
 * `shouldHaveReceived()` / `shouldHaveReceived(null)`, and `shouldNotHaveReceived()` /
 * `shouldNotHaveReceived(null)` return a HigherOrderMessage (or ExpectsHigherOrderMessage)
 * parameterized with the forwarded call's runtime return type.
 *
 * @internal
 */
final class NoArgumentFactoryProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    private const MOCK_CLASSES = [
        'Mockery\\Mock',
        'Mockery\\MockInterface',
        'Mockery\\LegacyMockInterface',
    ];

    private const HIGHER_ORDER_METHODS = [
        'shouldReceive',
        'shouldNotReceive',
        'allows',
        'expects',
        'shouldHaveReceived',
        'shouldNotHaveReceived',
    ];

    private const OPTIONAL_METHOD_VERIFIERS = [
        'shouldhavereceived',
        'shouldnothavereceived',
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::MOCK_CLASSES as $class) {
            foreach (self::HIGHER_ORDER_METHODS as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldReceive');
        $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldNotReceive');

        return $targets;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        if (!in_array(strtolower($context->invocation->name), self::OPTIONAL_METHOD_VERIFIERS, true)) {
            return null;
        }

        return new EffectiveCallableSignature(
            parameters: [
                new CallableParameter(
                    name: '$method',
                    type: Type::union(Type::string(), Type::null()),
                    hasDefault: true,
                ),
                new CallableParameter(
                    name: '$args',
                    type: Type::union(
                        Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
                        Type::namedObject('Closure'),
                        Type::null(),
                    ),
                    hasDefault: true,
                ),
            ],
            allowsNamedArguments: true,
            displayName: ($context->invocation->declaringClass ?? 'Mockery\\LegacyMockInterface')
                . '::'
                . $context->invocation->name,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        if (!self::isHigherOrderInvocation($invocation)) {
            return null;
        }

        $lower = strtolower($invocation->name);
        $mockType = $invocation->declaringClass === CompositeExpectationProvider::COMPOSITE
            ? CompositeExpectationProvider::extractMockType($invocation->receiverType)
            : $invocation->receiverType;

        $forwardedReturnType = match ($lower) {
            'shouldhavereceived' => Type::namedObject(VerificationProvider::DIRECTOR),
            'shouldnothavereceived' => Type::void(),
            default => CompositeExpectationProvider::typeWithMock($mockType),
        };

        return self::higherOrderType($lower, $forwardedReturnType, $mockType);
    }

    public static function higherOrderType(string $lower, Type $forwardedReturnType, ?Type $mockType): Type
    {
        $class = $lower === 'expects'
            ? 'Mockery\\ExpectsHigherOrderMessage'
            : 'Mockery\\HigherOrderMessage';

        $parameters = $mockType !== null ? [$forwardedReturnType, $mockType] : [$forwardedReturnType];

        return Type::fromAtomic(new NamedObjectType(
            name: $class,
            parameters: $parameters,
            variances: null,
            static: false,
            isThis: false,
            intersections: null,
            remappedParameters: false,
        ));
    }

    private static function isHigherOrderInvocation(Invocation $invocation): bool
    {
        $args = $invocation->arguments;
        if ($args === []) {
            return true;
        }

        $lower = strtolower($invocation->name);
        $firstType = $args[0]->type;
        if ($firstType === null) {
            return false;
        }

        if (in_array($lower, ['expects', 'shouldhavereceived', 'shouldnothavereceived'], true)) {
            return self::isNullType($firstType);
        }

        if ($lower === 'allows') {
            return self::isNullType($firstType) || self::isEmptyArrayType($firstType);
        }

        return false;
    }

    private static function isNullType(?Type $type): bool
    {
        if ($type === null || count($type->atomicTypes) !== 1) {
            return false;
        }

        $atomic = $type->atomicTypes[0];

        return $atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null;
    }

    private static function isEmptyArrayType(Type $type): bool
    {
        if (count($type->atomicTypes) !== 1) {
            return false;
        }

        $atomic = $type->atomicTypes[0];
        if ($atomic instanceof ListType) {
            return !$atomic->nonEmpty && (
                $atomic->knownCount === 0
                || ($atomic->knownElements === [] && self::isNeverType($atomic->elementType))
            );
        }

        if ($atomic instanceof KeyedArrayType) {
            return !$atomic->nonEmpty
                && $atomic->keyType === null
                && ($atomic->knownItems === null || $atomic->knownItems === []);
        }

        return false;
    }

    private static function isNeverType(Type $type): bool
    {
        if (count($type->atomicTypes) !== 1) {
            return false;
        }

        $atomic = $type->atomicTypes[0];

        return $atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Never;
    }
}
