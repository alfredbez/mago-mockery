<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function array_key_last;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_match_all;
use function stripos;
use function strtolower;
use function substr;

/**
 * Opt-in check (`MAGO_MOCKERY_STRICT_METHODS=1`) that validates literal method names
 * passed to Mockery expectation and verification calls against the mocked target class.
 */
final class StrictMockedMethodHook implements MethodCallAnalysisHook
{
    public const ISSUE_CODE = 'unknown-mocked-method';

    private const MOCK_CLASSES = [
        'Mockery\\Mock',
        'Mockery\\MockInterface',
        'Mockery\\LegacyMockInterface',
    ];

    private const IGNORED_TARGET_CLASSES = [
        'mockery\\mock' => true,
        'mockery\\mockinterface' => true,
        'mockery\\legacymockinterface' => true,
        'stdclass' => true,
    ];

    public function __construct(
        private readonly bool $variadicMethods,
    ) {}

    public function getTargets(): array
    {
        if ($this->variadicMethods) {
            $targets = [];
            foreach (self::MOCK_CLASSES as $class) {
                $targets[] = MethodTarget::exact($class, 'shouldReceive');
                $targets[] = MethodTarget::exact($class, 'shouldNotReceive');
            }
            $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldReceive');
            $targets[] = MethodTarget::exact(CompositeExpectationProvider::COMPOSITE, 'shouldNotReceive');

            return $targets;
        }

        $targets = [];
        foreach (self::MOCK_CLASSES as $class) {
            $targets[] = MethodTarget::exact($class, 'allows');
            $targets[] = MethodTarget::exact($class, 'expects');
            $targets[] = MethodTarget::exact($class, 'shouldHaveReceived');
            $targets[] = MethodTarget::exact($class, 'shouldNotHaveReceived');
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::SourceText,
            FileAnalysisRequirement::ReceiverType,
            FileAnalysisRequirement::ArgumentTypes,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $methodNames = $this->extractLiteralMethodNames($context->argumentTypes);
        if ($methodNames === []) {
            return;
        }

        $targetClasses = $this->extractTargetClasses($context->receiverType);
        if ($targetClasses === []) {
            return;
        }

        $metadataList = $context->codebase->getMultipleClassLikes($targetClasses);
        $validMetadata = [];
        $magicChecks = [];
        foreach ($metadataList as $index => $meta) {
            if (!$meta instanceof ClassLikeMetadata || $meta->hasIncompleteHierarchy() || $meta->mixins !== []) {
                return;
            }

            $className = $targetClasses[$index];
            $validMetadata[$className] = $meta;
            $magicChecks[] = new MemberIdentifier($className, '__call');
            $magicChecks[] = new MemberIdentifier($className, '__callStatic');
        }

        foreach ($context->codebase->checkMultipleMethodsExist($magicChecks) as $hasMagic) {
            if ($hasMagic) {
                return;
            }
        }

        $pseudoIndex = [];
        foreach ($validMetadata as $className => $meta) {
            foreach ($meta->pseudoMethods as $pseudo) {
                $pseudoIndex[strtolower($pseudo)] = true;
            }
            foreach ($meta->staticPseudoMethods as $pseudo) {
                $pseudoIndex[strtolower($pseudo)] = true;
            }
        }

        $targetLabel = implode('&', $targetClasses);
        foreach ($methodNames as $methodName) {
            if (isset($pseudoIndex[strtolower($methodName)])) {
                continue;
            }

            $checks = [];
            foreach ($targetClasses as $className) {
                $checks[] = new MemberIdentifier($className, $methodName);
            }

            if (in_array(true, $context->codebase->checkMultipleMethodsExist($checks), true)) {
                continue;
            }

            if ($this->isAllowedViaMockingMethod($context->source->contents, $context->node->span->end, $methodName)) {
                continue;
            }

            $context->report(
                Level::Error,
                self::ISSUE_CODE,
                Issue::new(
                    "Method `{$methodName}` does not exist on mocked type `{$targetLabel}`.",
                    $context->node->span,
                )->withHelp(
                    'Use an existing method on the mocked class, or unset MAGO_MOCKERY_STRICT_METHODS when mocking dynamic methods.',
                ),
            );
        }
    }

    /**
     * @param list<Type|null> $argumentTypes
     * @return list<string>
     */
    private function extractLiteralMethodNames(array $argumentTypes): array
    {
        if ($argumentTypes === []) {
            return [];
        }

        $toInspect = $this->variadicMethods ? $argumentTypes : [$argumentTypes[0]];
        $names = [];
        foreach ($toInspect as $type) {
            if ($type === null) {
                continue;
            }

            $literal = $type->getLiteralString();
            if ($literal !== null) {
                $first = explode('->', $literal, 2)[0];
                if ($first !== '') {
                    $names[] = $first;
                }

                continue;
            }

            if (count($type->atomicTypes) !== 1) {
                continue;
            }

            $atomic = $type->atomicTypes[0];
            if ($atomic instanceof KeyedArrayType && $atomic->knownItems !== null) {
                foreach ($atomic->knownItems as $item) {
                    if ($item->key->kind === ArrayKeyKind::String && is_string($item->key->value)) {
                        $first = explode('->', $item->key->value, 2)[0];
                        if ($first !== '') {
                            $names[] = $first;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<string>
     */
    private function extractTargetClasses(?Type $receiverType): array
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

            if (isset(self::IGNORED_TARGET_CLASSES[$lower])) {
                continue;
            }

            $classes[$lower] = $candidate;
        }

        return array_values($classes);
    }

    private function isAllowedViaMockingMethod(string $contents, int $beforeOffset, string $methodName): bool
    {
        if (stripos($contents, 'shouldAllowMockingMethod') === false) {
            return false;
        }

        $prefix = substr($contents, 0, $beforeOffset);
        if (preg_match_all('/\bfunction\b[^{]*\{/', $prefix, $fnMatches, \PREG_OFFSET_CAPTURE) >= 1) {
            $lastFn = $fnMatches[0][array_key_last($fnMatches[0])];
            $prefix = substr($prefix, $lastFn[1]);
        }

        if (preg_match_all('/->\s*shouldAllowMockingMethod\s*\(\s*([\'"])([^\'"]+)\1\s*\)/i', $prefix, $matches) < 1) {
            return false;
        }

        $wanted = strtolower($methodName);
        foreach ($matches[2] as $allowed) {
            if (strtolower($allowed) === $wanted) {
                return true;
            }
        }

        return false;
    }
}
