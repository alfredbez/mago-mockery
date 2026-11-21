<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer;

use AlfredBez\MagoMockery\Analyzer\Providers\CompositeExpectationProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\ExpectationAndMatcherSignatureProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\HigherOrderMessageProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockFactoryReturnTypeProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockHelperFunctionReturnTypeProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\NoArgumentFactoryProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\VerificationProvider;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

final class MockeryPlugin implements Plugin
{
    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'alfredbez/mockery',
            name: 'Mockery',
            description: 'Types Mockery mocks as an intersection of the mocked class and MockInterface, and resolves higher-order message calls.',
            defaultEnabled: true,
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->enableProviderMemoization();
        $registry->registerMethodReturnTypeProvider(new MockFactoryReturnTypeProvider());
        $registry->registerFunctionReturnTypeProvider(new MockHelperFunctionReturnTypeProvider());
        $registry->registerMethodReturnTypeProvider(new NoArgumentFactoryProvider());
        $registry->registerMethodReturnTypeProvider(new CompositeExpectationProvider());
        $registry->registerMethodReturnTypeProvider(new ExpectationAndMatcherSignatureProvider());
        $registry->registerMethodReturnTypeProvider(new VerificationProvider());
        $registry->registerMethodReturnTypeProvider(new HigherOrderMessageProvider());
    }
}
