<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * Mockery::mock(Foo::class) returns a Foo that is also a MockInterface.
 *
 * Mockery documents this as `LegacyMockInterface&MockInterface&TMock` with
 * `@param array<class-string<TMock>|...> $args`. Deriving TMock needs template
 * inference from a variadic array element, which Mago does not do, so the
 * extension supplies the intersection directly.
 *
 * @internal
 */
final class MockFactoryReturnTypeProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    private const FACTORY_DISPLAY_NAMES = [
        'mock' => 'mock',
        'spy' => 'spy',
        'namedmock' => 'namedMock',
        'instancemock' => 'instanceMock',
    ];

    public function __construct(
        private readonly ?NamedMockIndex $namedMocks = null,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Mockery', 'mock'),
            MethodTarget::exact('Mockery', 'spy'),
            MethodTarget::exact('Mockery', 'namedMock'),
            MethodTarget::exact('Mockery', 'instanceMock'),
            MethodTarget::exact('Mockery', 'fetchMock'),
            MethodTarget::exact('Mockery\\Container', 'mock'),
            MethodTarget::exact('Mockery\\Container', 'fetchMock'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $lower = strtolower($context->invocation->name);
        if (!isset(self::FACTORY_DISPLAY_NAMES[$lower])) {
            return null;
        }

        $argType = self::factoryArgumentType();
        $parameters = $lower === 'namedmock'
            ? [
                new CallableParameter(name: '$name', type: Type::string()),
                new CallableParameter(name: '$args', type: $argType, variadic: true),
            ]
            : [
                new CallableParameter(name: '$args', type: $argType, variadic: true),
            ];

        return new EffectiveCallableSignature(
            parameters: $parameters,
            allowsNamedArguments: true,
            displayName: ($context->invocation->declaringClass ?? 'Mockery') . '::' . self::FACTORY_DISPLAY_NAMES[$lower],
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        if (strtolower($context->invocation->name) === 'fetchmock') {
            return MockTypeFactory::fromFetchMock($context->invocation, $context->codebase, $this->namedMocks);
        }

        return MockTypeFactory::fromInvocation($context->invocation, $context->codebase);
    }

    public static function factoryArgumentType(): Type
    {
        return Type::union(
            Type::string(),
            Type::object(),
            Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
        );
    }
}
