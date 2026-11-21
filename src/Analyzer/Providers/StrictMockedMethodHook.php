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
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function stripos;
use function strlen;
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

            if ($this->isAllowedViaMockingMethod($context, $methodName)) {
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

    private function isAllowedViaMockingMethod(NodeAnalysisContext $context, string $methodName): bool
    {
        if (stripos($context->source->contents, 'shouldAllowMockingMethod') === false) {
            return false;
        }

        $source = $context->analysis->getSourceFile();
        $call = $this->findMethodCallBySpan($source, $context->node->span);
        if ($call === null) {
            return false;
        }

        $wanted = strtolower($methodName);
        if ($this->receiverChainAllowsMethod($source, $call, $wanted)) {
            return true;
        }

        $receiverVar = $this->baseReceiverVariableName($source, $call);
        if ($receiverVar === null) {
            return false;
        }

        $scope = $this->enclosingScope($source, $call);
        $callStart = $call->span->start;
        $stack = [];
        $scopeChildren = $source->getChildren($scope);
        for ($i = count($scopeChildren) - 1; $i >= 0; --$i) {
            $stack[] = $scopeChildren[$i];
        }

        while (($node = array_pop($stack)) !== null) {
            if ($node->span->start >= $callStart || self::isScopeBoundary($node->kind)) {
                continue;
            }

            if (
                $node->kind === NodeKind::MethodCall
                && $this->isShouldAllowMockingMethodCall($source, $node, $wanted)
                && $this->baseReceiverVariableName($source, $node) === $receiverVar
            ) {
                return true;
            }

            $children = $source->getChildren($node);
            for ($i = count($children) - 1; $i >= 0; --$i) {
                $stack[] = $children[$i];
            }
        }

        return false;
    }

    private function findMethodCallBySpan(SourceFile $source, Span $targetSpan): ?Node
    {
        $current = $source->getNode(0);
        if ($current->span->start <= $targetSpan->start && $current->span->end >= $targetSpan->end) {
            while (true) {
                if (
                    $current->kind === NodeKind::MethodCall
                    && $current->span->start === $targetSpan->start
                    && $current->span->end === $targetSpan->end
                ) {
                    return $current;
                }

                $next = null;
                foreach ($source->getChildren($current) as $child) {
                    if ($child->span->start <= $targetSpan->start && $child->span->end >= $targetSpan->end) {
                        $next = $child;
                        break;
                    }
                }
                if ($next === null) {
                    break;
                }
                $current = $next;
            }
        }

        foreach ($source->getNodes(NodeKind::MethodCall) as $methodCall) {
            if ($methodCall->span->start === $targetSpan->start && $methodCall->span->end === $targetSpan->end) {
                return $methodCall;
            }
        }

        return null;
    }

    private function receiverChainAllowsMethod(SourceFile $source, Node $call, string $wantedLower): bool
    {
        $receiver = $this->unwrap($source, $source->getChildren($call)[0] ?? null);
        while ($receiver !== null && $receiver->kind === NodeKind::MethodCall) {
            if ($this->isShouldAllowMockingMethodCall($source, $receiver, $wantedLower)) {
                return true;
            }

            $receiver = $this->unwrap($source, $source->getChildren($receiver)[0] ?? null);
        }

        return false;
    }

    private function baseReceiverVariableName(SourceFile $source, Node $call): ?string
    {
        $current = $this->unwrap($source, $source->getChildren($call)[0] ?? null);
        while ($current !== null && $current->kind === NodeKind::MethodCall) {
            $current = $this->unwrap($source, $source->getChildren($current)[0] ?? null);
        }

        if ($current === null || $current->kind !== NodeKind::DirectVariable) {
            return null;
        }

        return $source->getText($current);
    }

    private function isShouldAllowMockingMethodCall(SourceFile $source, Node $call, string $wantedLower): bool
    {
        $children = $source->getChildren($call);
        $member = $children[1] ?? null;
        if ($member === null) {
            return false;
        }

        $selector = $source->getChildren($member)[0] ?? null;
        if ($selector === null || $selector->kind !== NodeKind::LocalIdentifier) {
            return false;
        }

        if (strtolower($source->getText($selector)) !== 'shouldallowmockingmethod') {
            return false;
        }

        $argList = $children[2] ?? null;
        if ($argList === null || $argList->kind !== NodeKind::ArgumentList) {
            return false;
        }

        foreach ($source->getChildren($argList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }

            $value = null;
            if ($variant->kind === NodeKind::PositionalArgument) {
                $value = $source->getChildren($variant)[0] ?? null;
            } elseif ($variant->kind === NodeKind::NamedArgument) {
                $parts = $source->getChildren($variant);
                if (isset($parts[0], $parts[1]) && $source->getText($parts[0]) === 'method') {
                    $value = $parts[1];
                }
            }

            if ($value !== null) {
                $literal = $this->extractLiteralString($source, $value);
                if ($literal !== null && strtolower($literal) === $wantedLower) {
                    return true;
                }
            }

            break;
        }

        return false;
    }

    private function extractLiteralString(SourceFile $source, Node $node): ?string
    {
        $node = $this->unwrap($source, $node);
        if ($node === null) {
            return null;
        }

        $literal = $source->getLiteralString($node);
        if ($literal !== null) {
            return $literal;
        }

        $text = $source->getText($node);
        if (strlen($text) >= 2 && (($text[0] === "'" && $text[-1] === "'") || ($text[0] === '"' && $text[-1] === '"'))) {
            return substr($text, 1, -1);
        }

        return null;
    }

    private function enclosingScope(SourceFile $source, Node $node): Node
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if (self::isScopeBoundary($current->kind)) {
                return $current;
            }

            $parent = $source->getParent($current);
            if ($parent === null) {
                return $current;
            }

            $current = $parent;
        }

        return $node;
    }

    private static function isScopeBoundary(NodeKind $kind): bool
    {
        return $kind === NodeKind::Method
            || $kind === NodeKind::Function
            || $kind === NodeKind::Closure
            || $kind === NodeKind::ArrowFunction;
    }

    private function unwrap(SourceFile $source, ?Node $node): ?Node
    {
        while (
            $node !== null
            && (
                $node->kind === NodeKind::Expression
                || $node->kind === NodeKind::Call
                || $node->kind === NodeKind::Parenthesized
                || $node->kind === NodeKind::Variable
                || $node->kind === NodeKind::Literal
            )
        ) {
            $node = $source->getChildren($node)[0] ?? null;
        }

        return $node;
    }
}
