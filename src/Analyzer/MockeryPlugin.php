<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer;

use AlfredBez\MagoMockery\Analyzer\Providers\CompositeExpectationProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\ExpectationAndMatcherSignatureProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\HigherOrderMessageProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockeryPhpUnitInitializer;
use AlfredBez\MagoMockery\Analyzer\Providers\MockFactoryReturnTypeProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockHelperFunctionReturnTypeProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\NoArgumentFactoryProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\StrictMockedMethodHook;
use AlfredBez\MagoMockery\Analyzer\Providers\TraitMockConstantIssueFilter;
use AlfredBez\MagoMockery\Analyzer\Providers\VerificationProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

use function getenv;
use function in_array;
use function strtolower;
use function trim;

/**
 * @internal
 */
final class MockeryPlugin implements Plugin
{
    public const STRICT_METHODS_ENV = 'MAGO_MOCKERY_STRICT_METHODS';

    public function __construct(
        private readonly ?bool $strictMockedMethods = null,
    ) {}

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
        $registry->registerClassInitializerProvider(new MockeryPhpUnitInitializer());
        $registry->registerIssueFilterHook(new TraitMockConstantIssueFilter());
        $registry->registerEntryPoint(MethodTarget::exact('Mockery\\Adapter\\Phpunit\\MockeryTestCase', 'mockeryTestSetUp'));
        $registry->registerEntryPoint(MethodTarget::exact('Mockery\\Adapter\\Phpunit\\MockeryTestCase', 'mockeryTestTearDown'));
        $registry->registerEntryPoint(MethodTarget::exact('PHPUnit\\Framework\\TestCase', 'mockeryAssertPostConditions'));
        $registry->registerEntryPoint(MethodTarget::exact('PHPUnit\\Framework\\TestCase', 'startMockery'));
        $registry->registerEntryPoint(MethodTarget::exact('PHPUnit\\Framework\\TestCase', 'purgeMockeryContainer'));

        if ($this->isStrictMockedMethodsEnabled()) {
            $registry->registerMethodCallAnalysisHook(new StrictMockedMethodHook(StrictMockedMethodHook::MODE_VARIADIC));
            $registry->registerMethodCallAnalysisHook(new StrictMockedMethodHook(StrictMockedMethodHook::MODE_SINGLE));
            $registry->registerMethodCallAnalysisHook(new StrictMockedMethodHook(StrictMockedMethodHook::MODE_HIGHER_ORDER));
        }
    }

    private function isStrictMockedMethodsEnabled(): bool
    {
        if ($this->strictMockedMethods !== null) {
            return $this->strictMockedMethods;
        }

        $raw = getenv(self::STRICT_METHODS_ENV);
        if ($raw === false) {
            return false;
        }

        return in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }
}
