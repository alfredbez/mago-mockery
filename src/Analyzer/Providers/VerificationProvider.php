<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

/**
 * shouldHaveReceived('name') and shouldHaveBeenCalled() return a VerificationDirector,
 * while shouldNotHaveReceived('name') and shouldNotHaveBeenCalled() return void (null).
 *
 * The no-argument and explicit-null forms of shouldHaveReceived() / shouldNotHaveReceived()
 * return a HigherOrderMessage and belong to NoArgumentFactoryProvider.
 */
final class VerificationProvider implements MethodReturnTypeProvider
{
    public const DIRECTOR = 'Mockery\\VerificationDirector';

    private const VERIFIERS = [
        'shouldHaveReceived',
        'shouldNotHaveReceived',
        'shouldHaveBeenCalled',
        'shouldNotHaveBeenCalled',
    ];

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
        $lower = strtolower($context->invocation->name);
        if ($lower === 'shouldhavebeencalled') {
            return Type::namedObject(self::DIRECTOR);
        }

        if ($lower === 'shouldnothavebeencalled') {
            return Type::void();
        }

        $first = $context->invocation->arguments[0] ?? null;
        if ($first === null || self::containsNull($first->type)) {
            return null;
        }

        return $lower === 'shouldnothavereceived'
            ? Type::void()
            : Type::namedObject(self::DIRECTOR);
    }

    private static function containsNull(?Type $type): bool
    {
        if ($type === null) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                return true;
            }
        }

        return false;
    }
}
