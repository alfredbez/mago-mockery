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
 * Refines inaccurate or overly wide Mockery 1.6 parameter docblocks on
 * Expectation, VerificationDirector, and Mockery argument matcher factories.
 */
final class ExpectationAndMatcherSignatureProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Mockery\\Expectation', 'andSet'),
            MethodTarget::exact('Mockery\\Expectation', 'andReturnArg'),
            MethodTarget::exact('Mockery\\VerificationDirector', 'withArgs'),
            MethodTarget::exact('Mockery', 'type'),
            MethodTarget::exact('Mockery', 'pattern'),
            MethodTarget::exact('Mockery', 'ducktype'),
            MethodTarget::exact('Mockery', 'hasKey'),
            MethodTarget::exact('Mockery', 'isEqual'),
            MethodTarget::exact('Mockery', 'isSame'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $class = strtolower($context->invocation->declaringClass ?? '');
        $method = strtolower($context->invocation->name);

        if ($class === 'mockery\\expectation') {
            return match ($method) {
                'andset' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$name', type: Type::string()),
                        new CallableParameter(name: '$values', type: Type::mixed(), variadic: true),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery\\Expectation::andSet',
                ),
                'andreturnarg' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$index', type: Type::nonNegativeInt()),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery\\Expectation::andReturnArg',
                ),
                default => null,
            };
        }

        if ($class === 'mockery\\verificationdirector' && $method === 'withargs') {
            return new EffectiveCallableSignature(
                parameters: [
                    new CallableParameter(
                        name: '$args',
                        type: Type::union(
                            Type::array(Type::union(Type::int(), Type::string()), Type::mixed()),
                            Type::namedObject('Closure'),
                        ),
                    ),
                ],
                allowsNamedArguments: true,
                displayName: 'Mockery\\VerificationDirector::withArgs',
            );
        }

        if ($class === 'mockery') {
            return match ($method) {
                'type' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$expected', type: Type::string()),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery::type',
                ),
                'pattern' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$expected', type: Type::string()),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery::pattern',
                ),
                'ducktype' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$args', type: Type::string(), variadic: true),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery::ducktype',
                ),
                'haskey' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$key', type: Type::union(Type::int(), Type::string())),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery::hasKey',
                ),
                default => null,
            };
        }

        return null;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        if (strtolower($context->invocation->declaringClass ?? '') !== 'mockery') {
            return null;
        }

        return match (strtolower($context->invocation->name)) {
            'isequal' => Type::namedObject('Mockery\\Matcher\\IsEqual'),
            'issame' => Type::namedObject('Mockery\\Matcher\\IsSame'),
            default => null,
        };
    }
}
