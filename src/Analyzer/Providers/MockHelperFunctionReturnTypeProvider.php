<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\FunctionReturnTypeProvider;
use Mago\Sdk\Analyzer\FunctionTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * The global mock(), spy() and namedMock() helpers forward to Mockery::mock().
 *
 * @internal
 */
final class MockHelperFunctionReturnTypeProvider implements FunctionReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            FunctionTarget::exact('mock'),
            FunctionTarget::exact('spy'),
            FunctionTarget::exact('namedMock'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return MockTypeFactory::fromInvocation($context->invocation, $context->codebase);
    }
}
