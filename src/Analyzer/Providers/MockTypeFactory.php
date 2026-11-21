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

    private const MAX_CACHED_TYPES = 256;

    /** @var array<string, Type> */
    private static array $types = [];

    private const IGNORED_OBJECT_TYPES = [
        'closure' => true,
        'mockery\\generator\\mockconfigurationbuilder' => true,
        'mockery\\legacymockinterface' => true,
        'mockery\\mockinterface' => true,
    ];

    private function __construct() {}

    /**
     * Extracts mocked class/interface targets from mock()/spy()/namedMock()/instanceMock() arguments.
     *
     * Supports class-strings, Mockery string specs (`alias:`, `overload:`, `Foo[m1, m2]`,
     * comma-separated interface lists, pipe-separated specs), and concrete object partial mocks.
     * For namedMock(), argument 0 is the generated mock class name and is skipped.
     */
    public static function fromInvocation(Invocation $invocation, Codebase $codebase): ?Type
    {
        $startIndex = strtolower($invocation->name) === 'namedmock' ? 1 : 0;
        $classes = [];
        foreach ($invocation->arguments as $index => $argument) {
            if ($index < $startIndex || $argument->type === null) {
                continue;
            }

            $literal = $argument->type->getLiteralString();
            if ($literal !== null) {
                foreach (self::extractClassesFromSpec($literal) as $candidate) {
                    self::addTargetIfValid($candidate, $codebase, $classes);
                }

                continue;
            }

            foreach (self::extractObjectTargets($argument->type) as $candidate) {
                self::addTargetIfValid($candidate, $codebase, $classes);
            }
        }

        if ($classes === []) {
            return null;
        }

        return self::createMultiple(array_values($classes));
    }

    /**
     * @return list<string>
     */
    private static function extractClassesFromSpec(string $spec): array
    {
        $names = [];
        foreach (explode('|', $spec) as $part) {
            $part = trim($part);
            if ($part === '' || $part === 'null') {
                continue;
            }

            if (str_contains($part, ',') && !str_contains($part, ']')) {
                foreach (explode(',', $part) as $item) {
                    $candidate = ltrim(trim($item), '\\');
                    if ($candidate !== '') {
                        $names[] = $candidate;
                    }
                }

                continue;
            }

            if (str_starts_with($part, 'alias:')) {
                $candidate = ltrim(substr($part, 6), '\\');
                if ($candidate !== '') {
                    $names[] = $candidate;
                }

                continue;
            }

            if (str_starts_with($part, 'overload:')) {
                $candidate = ltrim(substr($part, 9), '\\');
                if ($candidate !== '') {
                    $names[] = $candidate;
                }

                continue;
            }

            if (str_ends_with($part, ']') && str_contains($part, '[')) {
                $candidate = ltrim(trim(explode('[', $part, 2)[0]), '\\');
                if ($candidate !== '') {
                    $names[] = $candidate;
                }

                continue;
            }

            $candidate = ltrim($part, '\\');
            if ($candidate !== '') {
                $names[] = $candidate;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function extractObjectTargets(Type $type): array
    {
        if (count($type->atomicTypes) !== 1) {
            return [];
        }

        $atomic = $type->atomicTypes[0];
        if (!$atomic instanceof NamedObjectType) {
            return [];
        }

        if (isset(self::IGNORED_OBJECT_TYPES[strtolower($atomic->name)])) {
            return [];
        }

        $targets = [$atomic->name];
        foreach ($atomic->intersections ?? [] as $intersection) {
            if ($intersection instanceof NamedObjectType) {
                $targets[] = $intersection->name;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, string> $classes
     */
    private static function addTargetIfValid(string $candidate, Codebase $codebase, array &$classes): void
    {
        $lower = strtolower($candidate);
        if (isset(self::IGNORED_OBJECT_TYPES[$lower]) || isset($classes[$lower])) {
            return;
        }

        if ($codebase->classOrInterfaceExists($candidate)) {
            $classes[$lower] = $candidate;
        }
    }

    /**
     * @param non-empty-list<string> $mockedClasses
     */
    private static function createMultiple(array $mockedClasses): Type
    {
        $key = implode("\0", $mockedClasses);
        if (isset(self::$types[$key])) {
            return self::$types[$key];
        }

        if (count(self::$types) >= self::MAX_CACHED_TYPES) {
            self::$types = [];
        }

        $primary = array_shift($mockedClasses);
        $intersections = [];
        foreach ($mockedClasses as $extra) {
            $intersections[] = new NamedObjectType($extra, null, null, false, false, null, false);
        }
        $intersections[] = new NamedObjectType(self::MOCK_INTERFACE, null, null, false, false, null, false);

        return self::$types[$key] = Type::fromAtomic(new NamedObjectType(
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
