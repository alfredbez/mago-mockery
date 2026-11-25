<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer;

use AlfredBez\MagoMockery\Analyzer\Providers\HigherOrderMessageProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use AlfredBez\MagoMockery\Analyzer\Providers\NamedMockIndex;
use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * Resolves span-dependent Mockery patterns (`fetchMock()` against same-file `namedMock()`
 * and higher-order forwarding calls explicitly allowed via `shouldAllowMockingMethod()`)
 * without disabling provider memoization on the main Mockery plugin.
 *
 * @internal
 */
final class NamedFetchMockPlugin implements Plugin, MethodReturnTypeProvider, CallableSignatureProvider
{
    private readonly NamedMockIndex $index;

    public function __construct()
    {
        $this->index = new NamedMockIndex();
    }

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'alfredbez/mockery-named-fetch',
            name: 'Mockery Named Fetch',
            description: 'Resolves same-file namedMock() targets and explicitly allowed higher-order calls.',
            defaultEnabled: true,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->registerCodebaseScanHook($this->index);
        $registry->registerMethodReturnTypeProvider($this);
    }

    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Mockery', 'fetchMock'),
            MethodTarget::exact('Mockery\\Container', 'fetchMock'),
            MethodTarget::allMethods('Mockery\\HigherOrderMessage'),
            MethodTarget::allMethods('Mockery\\ExpectsHigherOrderMessage'),
        ];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        if (!$this->isHigherOrderAllowedCall($context->invocation->declaringClass, $context->invocation->span, $context->invocation->name)) {
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
        $declaring = strtolower($context->invocation->declaringClass ?? '');
        if ($declaring === 'mockery' || $declaring === 'mockery\\container') {
            return MockTypeFactory::fromFetchMock($context->invocation, $context->codebase, $this->index);
        }

        if (!$this->isHigherOrderAllowedCall($context->invocation->declaringClass, $context->invocation->span, $context->invocation->name)) {
            return null;
        }

        return HigherOrderMessageProvider::extractForwardedReturnType($context->invocation->receiverType);
    }

    private function isHigherOrderAllowedCall(?string $declaringClass, \Mago\Sdk\Span $span, string $methodName): bool
    {
        $lower = strtolower($declaringClass ?? '');
        if ($lower !== 'mockery\\higherordermessage' && $lower !== 'mockery\\expectshigherordermessage') {
            return false;
        }

        return $this->index->isHigherOrderAllowed($span, $methodName);
    }
}
