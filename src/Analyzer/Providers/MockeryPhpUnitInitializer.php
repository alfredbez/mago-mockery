<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\ClassInitializerProvider;
use Mago\Sdk\Analyzer\ClassInitializerProviderContext;
use Mago\Sdk\Analyzer\ClassTarget;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

/**
 * Recognizes Mockery PHPUnit lifecycle initializers (`mockeryTestSetUp`, `startMockery`, `setUp`)
 * on MockeryTestCase subclasses and TestCase classes using MockeryPHPUnitIntegration.
 */
final class MockeryPhpUnitInitializer implements ClassInitializerProvider
{
    public function getTargets(): array
    {
        return [
            ClassTarget::exact('PHPUnit\\Framework\\TestCase'),
        ];
    }

    public function getClassInitializers(ClassInitializerProviderContext $context): array
    {
        $className = $context->class->name;
        [$hasMockeryTestSetUp, $hasStartMockery, $hasSetUp] = $context->codebase->checkMultipleMethodsExist([
            new MemberIdentifier($className, 'mockeryTestSetUp'),
            new MemberIdentifier($className, 'startMockery'),
            new MemberIdentifier($className, 'setUp'),
        ]);

        if (!$hasMockeryTestSetUp && !$hasStartMockery) {
            return [];
        }

        $initializers = [];
        if ($hasStartMockery) {
            $initializers[] = 'startMockery';
        }
        if ($hasSetUp) {
            $initializers[] = 'setUp';
        }
        if ($hasMockeryTestSetUp) {
            $initializers[] = 'mockeryTestSetUp';
        }

        return $initializers;
    }
}
