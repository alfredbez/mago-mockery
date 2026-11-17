<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * shouldHaveReceived('name') returns a VerificationDirector.
 *
 * LegacyMockInterface documents `@return self`, so `->with()->once()` chains sit
 * on the mock type and Mago reports them as missing methods. At runtime Mockery
 * returns a VerificationDirector, which declares the verification API.
 *
 * The no-argument form forwards to a HigherOrderMessage and stays with
 * NoArgumentFactoryProvider.
 */
final class VerificationProvider implements MethodReturnTypeProvider
{
    public const DIRECTOR = 'Mockery\\VerificationDirector';

    private const VERIFIERS = ['shouldHaveReceived', 'shouldNotHaveReceived', 'shouldHaveBeenCalled', 'shouldNotHaveBeenCalled'];

    public function getTargets(): array
    {
        $targets = [];
        foreach (['Mockery\\Mock', 'Mockery\\MockInterface', 'Mockery\\LegacyMockInterface'] as $class) {
            foreach (self::VERIFIERS as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        // shouldHaveReceived() / shouldNotHaveReceived() without a method name
        // return a higher-order message; the *BeenCalled forms always verify.
        if ($context->invocation->arguments === [] && !str_ends_with(strtolower($context->invocation->name), 'beencalled')) {
            return null;
        }

        return Type::namedObject(self::DIRECTOR);
    }
}
