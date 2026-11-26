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
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Refines inaccurate or overly wide Mockery 1.6 parameter docblocks on
 * Expectation, VerificationDirector, and Mockery argument matcher factories.
 *
 * @internal
 */
final class ExpectationAndMatcherSignatureProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    public function getTargets(): array
    {
        $targets = [
            MethodTarget::exact('Mockery\\Expectation', 'andSet'),
            MethodTarget::exact('Mockery\\Expectation', 'set'),
            MethodTarget::exact('Mockery\\Expectation', 'andReturnArg'),
            MethodTarget::exact('Mockery\\VerificationDirector', 'withArgs'),
            MethodTarget::exact('Mockery', 'type'),
            MethodTarget::exact('Mockery', 'pattern'),
            MethodTarget::exact('Mockery', 'ducktype'),
            MethodTarget::exact('Mockery', 'hasKey'),
            MethodTarget::exact('Mockery', 'isEqual'),
            MethodTarget::exact('Mockery', 'isSame'),
            MethodTarget::exact('Mockery', 'fetchMock'),
            MethodTarget::exact('Mockery\\Container', 'fetchMock'),
        ];

        foreach (['Mockery\\Mock', 'Mockery\\MockInterface', 'Mockery\\LegacyMockInterface'] as $class) {
            $targets[] = MethodTarget::exact($class, 'shouldIgnoreMissing');
            $targets[] = MethodTarget::exact($class, 'asUndefined');
        }

        // asUndefined() is declared on Mockery\Mock, not MockInterface. T&MockInterface
        // therefore looks up the call on T. anyClass() is the only target that fires.
        $targets[] = MethodTarget::anyClass('asUndefined');

        return $targets;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $class = strtolower($context->invocation->declaringClass ?? '');
        $method = strtolower($context->invocation->name);

        if ($class === 'mockery\\expectation') {
            return match ($method) {
                'andset', 'set' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$name', type: Type::string()),
                        new CallableParameter(name: '$values', type: Type::mixed(), variadic: true),
                    ],
                    allowsNamedArguments: true,
                    displayName: 'Mockery\\Expectation::' . $context->invocation->name,
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

        if ($method === 'asundefined' && self::isMockReceiver($context->invocation->receiverType)) {
            return new EffectiveCallableSignature(
                parameters: [],
                allowsNamedArguments: true,
                displayName: 'Mockery\\Mock::asUndefined',
            );
        }

        if (in_array($class, ['mockery\\mock', 'mockery\\mockinterface', 'mockery\\legacymockinterface'], true)) {
            return match ($method) {
                'shouldignoremissing' => new EffectiveCallableSignature(
                    parameters: [
                        new CallableParameter(name: '$returnValue', type: Type::mixed(), hasDefault: true),
                        new CallableParameter(name: '$recursive', type: Type::bool(), hasDefault: true),
                    ],
                    allowsNamedArguments: true,
                    displayName: ($context->invocation->declaringClass ?? 'Mockery\\LegacyMockInterface')
                        . '::shouldIgnoreMissing',
                ),
                'asundefined' => new EffectiveCallableSignature(
                    parameters: [],
                    allowsNamedArguments: true,
                    displayName: ($context->invocation->declaringClass ?? 'Mockery\\MockInterface') . '::asUndefined',
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

        if ($method === 'fetchmock') {
            $parameterName = $class === 'mockery\\container' ? '$reference' : '$name';

            return new EffectiveCallableSignature(
                parameters: [
                    new CallableParameter(name: $parameterName, type: Type::string()),
                ],
                allowsNamedArguments: true,
                displayName: ($context->invocation->declaringClass ?? 'Mockery') . '::fetchMock',
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
        $class = strtolower($context->invocation->declaringClass ?? '');
        $method = strtolower($context->invocation->name);

        if ($method === 'asundefined' && self::isMockReceiver($context->invocation->receiverType)) {
            return $context->invocation->receiverType;
        }

        if (in_array($class, ['mockery\\mock', 'mockery\\mockinterface', 'mockery\\legacymockinterface'], true)) {
            if ($method === 'asundefined' || $method === 'shouldignoremissing') {
                return $context->invocation->receiverType;
            }

            return null;
        }

        if ($class !== 'mockery') {
            return null;
        }

        return match ($method) {
            'isequal' => Type::namedObject('Mockery\\Matcher\\IsEqual'),
            'issame' => Type::namedObject('Mockery\\Matcher\\IsSame'),
            default => null,
        };
    }

    private static function isMockReceiver(?Type $receiverType): bool
    {
        if ($receiverType === null) {
            return false;
        }

        foreach ($receiverType->atomicTypes as $atomic) {
            if (self::namedObjectIsMockeryMock($atomic)) {
                continue;
            }

            $hasMockIntersection = false;
            if ($atomic instanceof NamedObjectType) {
                foreach ($atomic->intersections ?? [] as $intersection) {
                    if (self::namedObjectIsMockeryMock($intersection)) {
                        $hasMockIntersection = true;
                        break;
                    }
                }
            }

            if (!$hasMockIntersection) {
                return false;
            }
        }

        return true;
    }

    private static function namedObjectIsMockeryMock(object $atomic): bool
    {
        if (!$atomic instanceof NamedObjectType) {
            return false;
        }

        $lower = strtolower($atomic->name);

        return $lower === 'mockery\\mock'
            || $lower === 'mockery\\mockinterface'
            || $lower === 'mockery\\legacymockinterface';
    }
}
