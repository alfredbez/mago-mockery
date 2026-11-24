<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use WeakMap;

/**
 * HigherOrderMessage forwards calls to the underlying mock method.
 *
 * When produced by NoArgumentFactoryProvider, the receiver carries the forwarded
 * return type as parameter 0 (`CompositeExpectation<Mock>`, `VerificationDirector`,
 * or `void`) and the mocked type as parameter 1. Forwarded method names are checked
 * against the receiver's target set so unknown methods keep native diagnostics.
 */
final class HigherOrderMessageProvider implements MethodReturnTypeProvider, CallableSignatureProvider
{
    public const EXPECTATION = 'Mockery\\Expectation';

    private const MAX_CACHED_DECISIONS = 512;

    /** @var null|WeakMap<Codebase, array<string, bool>> */
    private ?WeakMap $forwardCache = null;

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
        if (!$this->shouldForward($context->invocation, $context->codebase)) {
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
        if ($forwarded === null || !$this->shouldForward($context->invocation, $context->codebase)) {
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

    private function shouldForward(Invocation $invocation, Codebase $codebase): bool
    {
        $methodName = $invocation->name;
        if (str_starts_with($methodName, '__')) {
            return false;
        }

        $mockType = self::extractMockType($invocation->receiverType);
        if ($mockType === null && self::extractForwardedReturnType($invocation->receiverType) === null) {
            return false;
        }

        if ($mockType === null) {
            return true;
        }

        $this->forwardCache ??= new WeakMap();
        $cached = $this->forwardCache[$codebase] ?? [];

        // Fast single-target key avoids re-extracting intersection lists on repeated calls.
        $atomic = count($mockType->atomicTypes) === 1 ? $mockType->atomicTypes[0] : null;
        if (
            $atomic instanceof NamedObjectType
            && $atomic->intersections !== null
            && count($atomic->intersections) === 1
            && $atomic->intersections[0] instanceof NamedObjectType
            && $atomic->intersections[0]->name === MockTypeFactory::MOCK_INTERFACE
        ) {
            $cacheKey = $atomic->name . "\1" . strtolower($methodName);
            if (isset($cached[$cacheKey])) {
                return $cached[$cacheKey];
            }
        } else {
            $cacheKey = null;
        }

        $targetClasses = MockTypeFactory::extractTargetClasses($mockType);
        if ($targetClasses === []) {
            return true;
        }

        $cacheKey ??= implode("\0", $targetClasses) . "\1" . strtolower($methodName);
        if (isset($cached[$cacheKey])) {
            return $cached[$cacheKey];
        }

        $validMetadata = MockTypeFactory::resolveStrictTargetMetadata($targetClasses, $codebase);
        $decision = $validMetadata === null
            || MockTypeFactory::methodExistsOnTargets($targetClasses, $validMetadata, $methodName, $codebase);

        if (count($cached) >= self::MAX_CACHED_DECISIONS) {
            $cached = [];
        }
        $cached[$cacheKey] = $decision;
        $this->forwardCache[$codebase] = $cached;

        return $decision;
    }

    private static function extractForwardedReturnType(?Type $receiverType): ?Type
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
