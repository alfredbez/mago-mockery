<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer;

use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use AlfredBez\MagoMockery\Analyzer\Providers\NamedMockIndex;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Resolves `Mockery::fetchMock()` and `Container::fetchMock()` calls against
 * same-file `namedMock()` registrations without disabling provider memoization
 * on the main Mockery plugin.
 */
final class NamedFetchMockPlugin implements Plugin, MethodReturnTypeProvider
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
            description: 'Resolves same-file namedMock() targets for Mockery::fetchMock() calls.',
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
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return MockTypeFactory::fromFetchMock($context->invocation, $context->codebase, $this->index);
    }
}
