<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;

/**
 * Builds `Foo&MockInterface` for a mocked class name.
 *
 * The SDK has no Type::intersection() helper. A named object whose
 * `intersections` list carries the remaining members is the encoding Mago
 * compares and resolves members through.
 *
 * @internal
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

            foreach (self::extractClassStringTargets($argument->type) as $candidate) {
                self::addTargetIfValid($candidate, $codebase, $classes);
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
    public static function extractClassesFromSpec(string $spec): array
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
     * @return list<NamedObjectType>
     */
    private static function extractClassStringTargets(Type $type): array
    {
        if (count($type->atomicTypes) !== 1) {
            return [];
        }

        $atomic = $type->atomicTypes[0];
        if (
            !$atomic instanceof ScalarType
            || $atomic->kind !== ScalarTypeKind::ClassLikeString
            || !$atomic->refinement instanceof ClassLikeStringType
        ) {
            return [];
        }

        $variant = $atomic->refinement->variant;
        if ($variant !== ClassLikeStringVariant::OfType && $variant !== ClassLikeStringVariant::Generic) {
            return [];
        }

        $constraint = $atomic->refinement->constraint;
        if (!$constraint instanceof NamedObjectType) {
            return [];
        }

        if (isset(self::IGNORED_OBJECT_TYPES[strtolower($constraint->name)])) {
            return [];
        }

        $targets = [$constraint];
        foreach ($constraint->intersections ?? [] as $intersection) {
            if ($intersection instanceof NamedObjectType) {
                $targets[] = $intersection;
            }
        }

        return $targets;
    }

    /**
     * @return list<NamedObjectType>
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

        $targets = [$atomic];
        foreach ($atomic->intersections ?? [] as $intersection) {
            if ($intersection instanceof NamedObjectType) {
                $targets[] = $intersection;
            }
        }

        return $targets;
    }

    /**
     * @param array<string, string|NamedObjectType> $classes
     */
    private static function addTargetIfValid(string|NamedObjectType $candidate, Codebase $codebase, array &$classes): void
    {
        $name = $candidate instanceof NamedObjectType ? $candidate->name : $candidate;
        $lower = strtolower($name);
        if (isset(self::IGNORED_OBJECT_TYPES[$lower]) || isset($classes[$lower])) {
            return;
        }

        if ($codebase->classOrInterfaceExists($name) || $codebase->traitExists($name)) {
            $classes[$lower] = $candidate;
        }
    }

    /**
     * Resolves Mockery::fetchMock($name) and Container::fetchMock($name) to `(T&MockInterface)|null`.
     */
    public static function fromFetchMock(Invocation $invocation, Codebase $codebase, ?NamedMockIndex $namedMocks = null): ?Type
    {
        $argType = $invocation->getArgument(0, 'name', 'reference')?->type;
        if ($argType === null) {
            return null;
        }

        $classes = [];
        $literal = $argType->getLiteralString();
        if ($literal !== null) {
            foreach (self::extractClassesFromSpec($literal) as $candidate) {
                self::addTargetIfValid($candidate, $codebase, $classes);
            }

            if ($classes === [] && $namedMocks !== null) {
                $indexed = $namedMocks->targetsForFetch($invocation->span, $literal);
                if ($indexed !== null) {
                    foreach ($indexed as $candidate) {
                        self::addTargetIfValid($candidate, $codebase, $classes);
                    }
                }
            }
        } else {
            foreach (self::extractClassStringTargets($argType) as $candidate) {
                self::addTargetIfValid($candidate, $codebase, $classes);
            }
        }

        if ($classes === []) {
            return null;
        }

        return Type::union(self::createMultiple(array_values($classes)), Type::null());
    }

    /**
     * @param non-empty-list<string|NamedObjectType> $mockedClasses
     */
    private static function createMultiple(array $mockedClasses): Type
    {
        $key = implode("\0", array_map(self::targetCacheKey(...), $mockedClasses));
        if (isset(self::$types[$key])) {
            return self::$types[$key];
        }

        if (count(self::$types) >= self::MAX_CACHED_TYPES) {
            self::$types = [];
        }

        $primary = array_shift($mockedClasses);
        $intersections = [];
        foreach ($mockedClasses as $extra) {
            $intersections[] = self::namedObjectForTarget($extra);
        }
        $intersections[] = new NamedObjectType(self::MOCK_INTERFACE, null, null, false, false, null, false);

        $primaryObject = self::namedObjectForTarget($primary, $intersections);

        return self::$types[$key] = Type::fromAtomic($primaryObject);
    }

    private static function targetCacheKey(string|NamedObjectType $target): string
    {
        if (is_string($target)) {
            return $target;
        }

        return self::atomicCacheKey($target);
    }

    private static function typeCacheKey(Type $type): string
    {
        $parts = [];
        foreach ($type->atomicTypes as $atomic) {
            $parts[] = self::atomicCacheKey($atomic);
        }

        return implode('|', $parts);
    }

    private static function atomicCacheKey(object $atomic): string
    {
        if ($atomic instanceof NamedObjectType) {
            $key = $atomic->name;
            if ($atomic->parameters !== null && $atomic->parameters !== []) {
                $key .= '<' . implode(',', array_map(self::typeCacheKey(...), $atomic->parameters)) . '>';
            }
            if ($atomic->intersections !== null && $atomic->intersections !== []) {
                $key .= '&' . implode('&', array_map(self::atomicCacheKey(...), $atomic->intersections));
            }

            return $key;
        }

        if ($atomic instanceof ScalarType) {
            $key = 'scalar:' . $atomic->kind->value;
            if ($atomic->refinement instanceof ClassLikeStringType) {
                $key .= ':' . $atomic->refinement->variant->name;
                if ($atomic->refinement->constraint instanceof AtomicType) {
                    $key .= ':' . self::atomicCacheKey($atomic->refinement->constraint);
                }
            } elseif (is_bool($atomic->refinement)) {
                $key .= ':' . ($atomic->refinement ? 'true' : 'false');
            }

            return $key;
        }

        if ($atomic instanceof KeyedArrayType) {
            $key = $atomic->nonEmpty ? 'nonempty-array' : 'array';
            if ($atomic->keyType !== null && $atomic->valueType !== null) {
                $key .= '<' . self::typeCacheKey($atomic->keyType) . ',' . self::typeCacheKey($atomic->valueType) . '>';
            }

            return $key;
        }

        if ($atomic instanceof ListType) {
            $prefix = $atomic->nonEmpty ? 'nonempty-list' : 'list';

            return $prefix . '<' . self::typeCacheKey($atomic->elementType) . '>';
        }

        if ($atomic instanceof SimpleAtomicType) {
            return 'simple:' . $atomic->kind->value;
        }

        return $atomic::class;
    }

    /**
     * @param list<NamedObjectType>|null $intersections
     */
    private static function namedObjectForTarget(string|NamedObjectType $target, ?array $intersections = null): NamedObjectType
    {
        if (is_string($target)) {
            return new NamedObjectType($target, null, null, false, false, $intersections, false);
        }

        return new NamedObjectType(
            name: $target->name,
            parameters: $target->parameters,
            variances: $target->variances,
            static: false,
            isThis: false,
            intersections: $intersections,
            remappedParameters: $target->remappedParameters,
        );
    }

    /**
     * Extracts concrete/interface/trait target class names from a mock intersection type.
     *
     * Returns an empty list when the mock is anonymous, includes `stdClass`, or cannot
     * be narrowed to a single named object intersection.
     *
     * @return list<string>
     */
    public static function extractTargetClasses(?Type $receiverType): array
    {
        if ($receiverType === null) {
            return [];
        }

        $unwrapped = CompositeExpectationProvider::extractMockType($receiverType);
        $mockType = $unwrapped ?? $receiverType;

        if (count($mockType->atomicTypes) !== 1) {
            return [];
        }

        $atomic = $mockType->atomicTypes[0];
        if (!$atomic instanceof NamedObjectType) {
            return [];
        }

        $classes = [];
        $candidates = [$atomic->name];
        foreach ($atomic->intersections ?? [] as $intersection) {
            if ($intersection instanceof NamedObjectType) {
                $candidates[] = $intersection->name;
            }
        }

        foreach ($candidates as $candidate) {
            $lower = strtolower($candidate);
            if ($lower === 'stdclass') {
                return [];
            }

            if (
                $lower === 'mockery\\mock'
                || $lower === 'mockery\\mockinterface'
                || $lower === 'mockery\\legacymockinterface'
            ) {
                continue;
            }

            $classes[$lower] = $candidate;
        }

        return array_values($classes);
    }

    /**
     * Resolves class metadata for strict target validation, returning null when any target
     * has an incomplete hierarchy, `@mixin`, `__call`, or `__callStatic`.
     *
     * @param non-empty-list<string> $targetClasses
     * @return array<string, ClassLikeMetadata>|null
     */
    public static function resolveStrictTargetMetadata(array $targetClasses, Codebase $codebase): ?array
    {
        $metadataList = $codebase->getMultipleClassLikes($targetClasses);
        $validMetadata = [];
        $magicChecks = [];
        foreach ($metadataList as $index => $meta) {
            if (!$meta instanceof ClassLikeMetadata || $meta->hasIncompleteHierarchy() || $meta->mixins !== []) {
                return null;
            }

            $className = $targetClasses[$index];
            $validMetadata[$className] = $meta;
            $magicChecks[] = new MemberIdentifier($className, '__call');
            $magicChecks[] = new MemberIdentifier($className, '__callStatic');
        }

        foreach ($codebase->checkMultipleMethodsExist($magicChecks) as $hasMagic) {
            if ($hasMagic) {
                return null;
            }
        }

        return $validMetadata;
    }

    /**
     * @param non-empty-list<string> $targetClasses
     * @param array<string, ClassLikeMetadata> $validMetadata
     */
    public static function methodExistsOnTargets(
        array $targetClasses,
        array $validMetadata,
        string $methodName,
        Codebase $codebase,
    ): bool {
        $lower = strtolower($methodName);
        foreach ($validMetadata as $meta) {
            foreach ($meta->pseudoMethods as $pseudo) {
                if (strtolower($pseudo) === $lower) {
                    return true;
                }
            }
            foreach ($meta->staticPseudoMethods as $pseudo) {
                if (strtolower($pseudo) === $lower) {
                    return true;
                }
            }
        }

        $checks = [];
        foreach ($targetClasses as $className) {
            $checks[] = new MemberIdentifier($className, $methodName);
        }

        return in_array(true, $codebase->checkMultipleMethodsExist($checks), true);
    }
}
