<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\Visibility;
use WeakMap;

/**
 * shouldReceive('name') returns a CompositeExpectation, not an Expectation.
 *
 * Mockery documents `ExpectationInterface|Expectation|HigherOrderMessage`, but
 * Mockery::parseShouldReturnArgs() always returns a CompositeExpectation - even
 * for a single method name. Typing the declared union leaves `with()`, `once()`
 * and a second `shouldReceive()` unresolvable, because the union also contains
 * members that do not declare them.
 *
 * Declared methods on CompositeExpectation (`add`, `getMock`, `mock`,
 * `getOrderNumber`, `__toString`, `andReturn`, `andReturns`, `shouldReceive`,
 * `shouldNotReceive`) keep their real signatures and return types. Methods
 * forwarded through `__call()` are resolved against `Mockery\Expectation` so
 * unknown methods and invalid argument types are still reported.
 *
 * @internal
 */
final class CompositeExpectationProvider implements MethodReturnTypeProvider, CallableSignatureProvider
{
    public const COMPOSITE = 'Mockery\\CompositeExpectation';
    public const EXPECTATION = 'Mockery\\Expectation';

    /** @var null|WeakMap<FunctionLikeMetadata, array<string, list<CallableParameter>>> */
    private ?WeakMap $parameters = null;

    /** Methods that build an expectation through Mockery::parseShouldReturnArgs(). */
    private const FACTORIES = ['shouldReceive', 'shouldNotReceive', 'expects', 'allows'];

    /** Methods declared directly on CompositeExpectation (not forwarded via __call). */
    private const DECLARED_ON_COMPOSITE = [
        '__call' => true,
        '__tostring' => true,
        'add' => true,
        'andreturn' => true,
        'andreturns' => true,
        'getmock' => true,
        'getordernumber' => true,
        'mock' => true,
        'shouldnotreceive' => true,
        'shouldreceive' => true,
    ];

    public function getTargets(): array
    {
        $targets = [MethodTarget::allMethods(self::COMPOSITE)];
        foreach (['Mockery\\Mock', 'Mockery\\MockInterface', 'Mockery\\LegacyMockInterface'] as $class) {
            foreach (self::FACTORIES as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        if ($context->invocation->declaringClass !== self::COMPOSITE) {
            return null;
        }

        $methodName = $context->invocation->name;
        $lower = strtolower($methodName);
        if (isset(self::DECLARED_ON_COMPOSITE[$lower])) {
            // Keep CompositeExpectation's declared signatures (`add(Expectation)`, `getMock()`, etc.).
            return null;
        }

        $expectationMethod = $this->resolveForwardedMethod($context->codebase, $methodName);
        if ($expectationMethod === null) {
            // Unknown or non-public methods must remain unresolved so Mago flags them.
            return null;
        }

        if ($expectationMethod->parameters === []) {
            return new EffectiveCallableSignature(
                parameters: [],
                allowsNamedArguments: true,
                displayName: self::COMPOSITE . '::' . $methodName,
            );
        }

        if ($lower === 'set') {
            return new EffectiveCallableSignature(
                parameters: [
                    new CallableParameter(name: '$name', type: Type::string()),
                    new CallableParameter(name: '$value', type: Type::mixed()),
                    new CallableParameter(name: '$values', type: Type::mixed(), variadic: true),
                ],
                allowsNamedArguments: false,
                displayName: self::COMPOSITE . '::' . $methodName,
            );
        }

        $this->parameters ??= new WeakMap();
        $parameters = $this->parameters[$expectationMethod][$lower] ?? null;
        if ($parameters === null) {
            $parameters = [];
            foreach ($expectationMethod->parameters as $parameter) {
                $name = str_starts_with($parameter->name, '$') ? $parameter->name : '$' . $parameter->name;
                $variadic = $parameter->flags->contains(MetadataFlags::VARIADIC);
                $hasDefault = !$variadic && $parameter->flags->contains(MetadataFlags::HAS_DEFAULT);
                $byReference = $parameter->flags->contains(MetadataFlags::BY_REFERENCE);
                $type = $parameter->type?->type ?? $parameter->declaredType?->type;

                // Mockery's Expectation::andSet() docblock mistakenly types variadic values as `array<mixed> ...$values`.
                if ($lower === 'andset' && $variadic) {
                    $type = Type::mixed();
                } elseif ($lower === 'andreturnarg' && !$variadic) {
                    $type = Type::nonNegativeInt();
                }

                $parameters[] = new CallableParameter(
                    name: $name,
                    type: $type,
                    closureThisType: $parameter->closureThisType?->type,
                    byReference: $byReference,
                    variadic: $variadic,
                    hasDefault: $hasDefault,
                    outType: $parameter->outType?->type,
                );
            }

            $cached = $this->parameters[$expectationMethod] ?? [];
            $cached[$lower] = $parameters;
            $this->parameters[$expectationMethod] = $cached;
        }

        return new EffectiveCallableSignature(
            parameters: $parameters,
            allowsNamedArguments: true,
            displayName: self::COMPOSITE . '::' . $methodName,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $methodName = $context->invocation->name;

        if ($context->invocation->declaringClass === self::COMPOSITE) {
            $lower = strtolower($methodName);
            if ($lower === 'getmock' || $lower === 'mock') {
                return self::extractMockType($context->invocation->receiverType);
            }

            if ($lower === 'shouldreceive' || $lower === 'shouldnotreceive') {
                if ($context->invocation->arguments === []) {
                    // Zero-arg form belongs to NoArgumentFactoryProvider (HigherOrderMessage).
                    return null;
                }

                return self::typeWithMock(self::extractMockType($context->invocation->receiverType));
            }

            if (isset(self::DECLARED_ON_COMPOSITE[$lower])) {
                if ($lower === 'andreturn' || $lower === 'andreturns') {
                    return self::typeWithMock(self::extractMockType($context->invocation->receiverType));
                }

                // `add()` -> void, `getOrderNumber()` -> int, `__toString()` -> string.
                return null;
            }

            if ($this->resolveForwardedMethod($context->codebase, $methodName) === null) {
                return null;
            }

            return self::typeWithMock(self::extractMockType($context->invocation->receiverType));
        }

        $first = $context->invocation->arguments[0] ?? null;
        if ($first === null) {
            // The no-argument form returns a higher-order message; that split
            // belongs to NoArgumentFactoryProvider.
            return null;
        }

        $lower = strtolower($methodName);
        if ($lower === 'allows') {
            if ($first->type !== null && self::isStringType($first->type)) {
                return self::typeWithMock($context->invocation->receiverType);
            }

            if ($first->type !== null && self::isNonEmptyArrayType($first->type)) {
                return $context->invocation->receiverType;
            }

            if ($first->type !== null && self::isPossiblyEmptyArrayType($first->type)) {
                $mockType = $context->invocation->receiverType;

                return Type::union(
                    NoArgumentFactoryProvider::higherOrderType(
                        'allows',
                        self::typeWithMock($mockType),
                        $mockType,
                    ),
                    $mockType ?? Type::namedObject(MockTypeFactory::MOCK_INTERFACE),
                );
            }

            return null;
        }

        if ($lower === 'expects') {
            return $first->type !== null && self::isStringType($first->type)
                ? self::typeWithMock($context->invocation->receiverType)
                : null;
        }

        return self::typeWithMock($context->invocation->receiverType);
    }

    public static function typeWithMock(?Type $mockType): Type
    {
        if ($mockType === null) {
            return Type::namedObject(self::COMPOSITE);
        }

        return Type::fromAtomic(new NamedObjectType(
            name: self::COMPOSITE,
            parameters: [$mockType],
            variances: null,
            static: false,
            isThis: false,
            intersections: null,
            remappedParameters: false,
        ));
    }

    public static function extractMockType(?Type $compositeType): ?Type
    {
        if ($compositeType === null) {
            return null;
        }

        foreach ($compositeType->atomicTypes as $atomic) {
            if (
                $atomic instanceof NamedObjectType
                && strtolower($atomic->name) === strtolower(self::COMPOSITE)
                && $atomic->parameters !== null
                && isset($atomic->parameters[0])
            ) {
                return $atomic->parameters[0];
            }
        }

        return null;
    }

    private function resolveForwardedMethod(Codebase $codebase, string $methodName): ?FunctionLikeMetadata
    {
        if (str_starts_with($methodName, '__')) {
            return null;
        }

        $method = $codebase->getMethod(self::EXPECTATION, $methodName);
        if ($method === null || $method->static || $method->constructor) {
            return null;
        }

        if ($method->visibility !== null && $method->visibility !== Visibility::Public) {
            return null;
        }

        return $method;
    }

    private static function isStringType(Type $type): bool
    {
        foreach ($type->atomicTypes as $atomic) {
            if (
                !$atomic instanceof ScalarType
                || ($atomic->kind !== ScalarTypeKind::String && $atomic->kind !== ScalarTypeKind::ClassLikeString)
            ) {
                return false;
            }
        }

        return true;
    }

    private static function isNonEmptyArrayType(Type $type): bool
    {
        $emptiness = self::classifyArrayEmptiness($type);

        return $emptiness !== null && !$emptiness['canBeEmpty'] && $emptiness['canBeNonEmpty'];
    }

    private static function isPossiblyEmptyArrayType(Type $type): bool
    {
        $emptiness = self::classifyArrayEmptiness($type);

        return $emptiness !== null && $emptiness['canBeEmpty'] && $emptiness['canBeNonEmpty'];
    }

    /**
     * @return array{canBeEmpty: bool, canBeNonEmpty: bool}|null
     */
    private static function classifyArrayEmptiness(Type $type): ?array
    {
        $canBeEmpty = false;
        $canBeNonEmpty = false;

        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof ListType) {
                if (
                    !$atomic->nonEmpty
                    && ($atomic->knownCount === 0 || ($atomic->knownElements === [] && self::isNeverType($atomic->elementType)))
                ) {
                    $canBeEmpty = true;
                    continue;
                }

                if ($atomic->nonEmpty || ($atomic->knownCount !== null && $atomic->knownCount > 0)) {
                    $canBeNonEmpty = true;
                    continue;
                }

                $canBeEmpty = true;
                $canBeNonEmpty = true;
                continue;
            }

            if ($atomic instanceof KeyedArrayType) {
                if (
                    !$atomic->nonEmpty
                    && $atomic->keyType === null
                    && ($atomic->knownItems === null || $atomic->knownItems === [])
                ) {
                    $canBeEmpty = true;
                    continue;
                }

                if ($atomic->nonEmpty) {
                    $canBeNonEmpty = true;
                    continue;
                }

                $canBeEmpty = true;
                $canBeNonEmpty = true;
                continue;
            }

            return null;
        }

        return ['canBeEmpty' => $canBeEmpty, 'canBeNonEmpty' => $canBeNonEmpty];
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
