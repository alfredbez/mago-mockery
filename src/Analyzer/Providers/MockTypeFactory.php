<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

/**
 * Builds `Foo&MockInterface` for a mocked class name.
 *
 * The SDK has no Type::intersection() helper. A named object whose
 * `intersections` list carries the remaining members is the encoding Mago
 * compares and resolves members through.
 */
final class MockTypeFactory
{
    public const MOCK_INTERFACE = 'Mockery\\MockInterface';

    private function __construct() {}

    /**
     * The mocked classes are class-string arguments for mock()/spy(), and start at
     * index 1 for namedMock(), where argument 0 is the mock name.
     */
    public static function fromInvocation(Invocation $invocation, Codebase $codebase): ?Type
    {
        $startIndex = strtolower($invocation->name) === 'namedmock' ? 1 : 0;
        $classes = [];
        foreach ($invocation->arguments as $index => $argument) {
            if ($index < $startIndex) {
                continue;
            }

            $class = $argument->type?->getLiteralClassString();
            if ($class !== null && $codebase->classOrInterfaceExists($class)) {
                $classes[$class] = true;
            }
        }

        if ($classes === []) {
            return null;
        }

        return self::createMultiple(array_keys($classes));
    }

    public static function create(string $mocked, Codebase $codebase): ?Type
    {
        if (!$codebase->classOrInterfaceExists($mocked)) {
            return null;
        }

        return self::createMultiple([$mocked]);
    }

    /**
     * @param non-empty-list<string> $mockedClasses
     */
    private static function createMultiple(array $mockedClasses): Type
    {
        $primary = array_shift($mockedClasses);
        $intersections = [];
        foreach ($mockedClasses as $extra) {
            $intersections[] = new NamedObjectType($extra, null, null, false, false, null, false);
        }
        $intersections[] = new NamedObjectType(self::MOCK_INTERFACE, null, null, false, false, null, false);

        return Type::fromAtomic(new NamedObjectType(
            name: $primary,
            parameters: null,
            variances: null,
            static: false,
            isThis: false,
            intersections: $intersections,
            remappedParameters: false,
        ));
    }
}
