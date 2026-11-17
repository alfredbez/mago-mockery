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
 * Factory and verification methods called without arguments return a higher-order message.
 *
 * `shouldReceive()`, `shouldNotReceive()`, `expects()`, `shouldHaveReceived()`, and
 * `shouldNotHaveReceived()` switch to HigherOrderMessage / ExpectsHigherOrderMessage
 * when invoked with no arguments. `LegacyMockInterface::shouldHaveReceived()` and
 * `shouldNotHaveReceived()` declare `$method` without a default, so this provider also
 * supplies the optional zero-argument signature when called without arguments.
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

        $targets[] = MethodTarget::exact('Mockery\\CompositeExpectation', 'shouldReceive');
        $targets[] = MethodTarget::exact('Mockery\\CompositeExpectation', 'shouldNotReceive');

        return $targets;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        if ($context->invocation->arguments !== []) {
            return null;
        }

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
                    type: Type::mixed(),
                    hasDefault: true,
                ),
            ],
            allowsNamedArguments: false,
            displayName: ($context->invocation->declaringClass ?? 'Mockery\\LegacyMockInterface')
                . '::'
                . $context->invocation->name,
        );
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        if ($context->invocation->arguments !== []) {
            return null;
        }

        return Type::namedObject(
            strtolower($context->invocation->name) === 'expects'
                ? 'Mockery\\ExpectsHigherOrderMessage'
                : 'Mockery\\HigherOrderMessage',
        );
    }
}
