<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\FunctionReturnTypeProvider;
use Mago\Sdk\Analyzer\FunctionTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * The global mock(), spy() and namedMock() helpers forward to Mockery::mock().
 *
 * @internal
 */
final class MockHelperFunctionReturnTypeProvider implements FunctionReturnTypeProvider, CallableSignatureOverride
{
    private const HELPER_DISPLAY_NAMES = [
        'mock' => 'mock',
        'spy' => 'spy',
        'namedmock' => 'namedMock',
    ];

    public function getTargets(): array
    {
        return [
            FunctionTarget::exact('mock'),
            FunctionTarget::exact('spy'),
            FunctionTarget::exact('namedMock'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $lower = strtolower($context->invocation->name);
        $argType = MockFactoryReturnTypeProvider::factoryArgumentType();
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
            displayName: self::HELPER_DISPLAY_NAMES[$lower] ?? $context->invocation->name,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return MockTypeFactory::fromInvocation($context->invocation, $context->codebase);
    }
}
