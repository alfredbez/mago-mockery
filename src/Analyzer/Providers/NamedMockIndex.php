<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\CodebaseScanContext;
use Mago\Sdk\Analyzer\CodebaseScanHook;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function array_values;
use function count;
use function ltrim;
use function preg_match;
use function stripos;
use function strlen;
use function strpos;
use function strtolower;
use function substr;

/**
 * Indexes same-file `namedMock()` targets for `fetchMock()` calls by exact call span.
 *
 * Cross-file `fetchMock()` calls remain unresolved; span collisions across files with
 * differing targets degrade conservatively to unresolved.
 *
 * @internal
 */
final class NamedMockIndex implements CodebaseScanHook
{
    private const LIFECYCLE_METHODS = [
        'setup' => true,
        'startmockery' => true,
        'mockerytestsetup' => true,
    ];

    /** @var array<string, list<string>|null> */
    private array $fetchTargetsByKey = [];

    /** @var array<string, true> */
    private array $ambiguousKeys = [];

    public function getTargets(): array
    {
        return ['**/*.php'];
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->fetchTargetsByKey = [];
            $this->ambiguousKeys = [];
        }

        foreach ($context->files as $source) {
            if (stripos($source->contents, 'fetchMock') === false) {
                continue;
            }

            $fileAliases = stripos($source->contents, 'namedMock') !== false
                ? self::collectFileNamedMocks($source)
                : ['methods' => [], 'lifecycle' => []];

            self::recordFileFetchCalls($source, $fileAliases, $this->fetchTargetsByKey, $this->ambiguousKeys);
        }
    }

    /**
     * @return list<string>|null
     */
    public function targetsForFetch(Span $span, string $name): ?array
    {
        $key = $span->start . ':' . $span->end . "\0" . $name;
        if (isset($this->ambiguousKeys[$key])) {
            return null;
        }

        return $this->fetchTargetsByKey[$key] ?? null;
    }

    /**
     * @return array{
     *     methods: array<string, array<string, list<array{end: int, targets: list<string>|null}>>>,
     *     lifecycle: array<string, array<string, list<string>|null>>
     * }
     */
    private static function collectFileNamedMocks(SourceFile $source): array
    {
        $aliases = ['methods' => [], 'lifecycle' => []];

        foreach ($source->getNodes(NodeKind::StaticMethodCall) as $call) {
            $method = self::calledStaticMethodName($source, $call);
            if ($method === null || strtolower($method) !== 'namedmock') {
                continue;
            }
            if (!self::isMockeryStaticReceiver($source, $call)) {
                continue;
            }
            self::extractNamedMockCall($source, $call, $aliases);
        }

        foreach ($source->getNodes(NodeKind::FunctionCall) as $call) {
            $func = self::calledFunctionName($source, $call);
            if ($func === null || strtolower(ltrim($func, '\\')) !== 'namedmock') {
                continue;
            }
            self::extractNamedMockCall($source, $call, $aliases);
        }

        return $aliases;
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<array{end: int, targets: list<string>|null}>>>,
     *     lifecycle: array<string, array<string, list<string>|null>>
     * } $aliases
     */
    private static function extractNamedMockCall(SourceFile $source, Node $call, array &$aliases): void
    {
        $argList = self::findChildOfKind($source, $call, NodeKind::ArgumentList);
        if ($argList === null) {
            return;
        }

        $argValues = self::positionalArgumentValues($source, $argList);
        if (!isset($argValues[0])) {
            return;
        }

        $aliasName = self::extractLiteralString($source, $argValues[0]);
        if ($aliasName === null || $aliasName === '') {
            return;
        }

        $targets = [];
        for ($i = 1, $total = count($argValues); $i < $total; ++$i) {
            foreach (self::extractClassCandidatesFromNode($source, $argValues[$i]) as $candidate) {
                $targets[strtolower($candidate)] = $candidate;
            }
        }

        $resolved = $targets !== [] ? array_values($targets) : null;
        $callable = self::enclosingCallable($source, $call);
        if ($callable === null) {
            return;
        }

        if (self::isLifecycleCallable($source, $callable)) {
            $classKey = self::enclosingClassKey($source, $call);
            if ($classKey !== null) {
                $aliases['lifecycle'][$classKey] ??= [];
                if (array_key_exists($aliasName, $aliases['lifecycle'][$classKey])) {
                    if ($aliases['lifecycle'][$classKey][$aliasName] !== $resolved) {
                        $aliases['lifecycle'][$classKey][$aliasName] = null;
                    }
                    return;
                }
                $aliases['lifecycle'][$classKey][$aliasName] = $resolved;
                return;
            }
        }

        $methodKey = $callable->span->start . ':' . $callable->span->end;
        $aliases['methods'][$methodKey][$aliasName][] = [
            'end' => $call->span->end,
            'targets' => $resolved,
        ];
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<array{end: int, targets: list<string>|null}>>>,
     *     lifecycle: array<string, array<string, list<string>|null>>
     * } $fileAliases
     * @param array<string, list<string>|null> $fetchTargetsByKey
     * @param array<string, true> $ambiguousKeys
     */
    private static function recordFileFetchCalls(
        SourceFile $source,
        array $fileAliases,
        array &$fetchTargetsByKey,
        array &$ambiguousKeys,
    ): void {
        foreach ([NodeKind::StaticMethodCall, NodeKind::MethodCall] as $kind) {
            foreach ($source->getNodes($kind) as $call) {
                $method = $kind === NodeKind::StaticMethodCall
                    ? self::calledStaticMethodName($source, $call)
                    : self::calledInstanceMethodName($source, $call);
                if ($method === null || strtolower($method) !== 'fetchmock') {
                    continue;
                }
                if ($kind === NodeKind::StaticMethodCall && !self::isMockeryStaticReceiver($source, $call)) {
                    continue;
                }

                $argList = self::findChildOfKind($source, $call, NodeKind::ArgumentList);
                if ($argList === null) {
                    continue;
                }

                $nameNode = self::findFetchArgument($source, $argList);
                if ($nameNode === null) {
                    continue;
                }

                $fetchName = self::extractLiteralString($source, $nameNode);
                if ($fetchName === null || $fetchName === '') {
                    continue;
                }

                $key = $call->span->start . ':' . $call->span->end . "\0" . $fetchName;
                if (isset($ambiguousKeys[$key])) {
                    continue;
                }

                $targets = self::targetsForFetchInScope($source, $call, $fetchName, $fileAliases);
                if (array_key_exists($key, $fetchTargetsByKey)) {
                    if ($fetchTargetsByKey[$key] !== $targets) {
                        $ambiguousKeys[$key] = true;
                        unset($fetchTargetsByKey[$key]);
                    }
                    continue;
                }

                $fetchTargetsByKey[$key] = $targets;
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function extractClassCandidatesFromNode(SourceFile $source, Node $node): array
    {
        $stringVal = self::evaluateStaticStringExpression($source, $node);
        if ($stringVal !== null && $stringVal !== '') {
            return MockTypeFactory::extractClassesFromSpec($stringVal);
        }

        return [];
    }

    private static function evaluateStaticStringExpression(SourceFile $source, Node $node): ?string
    {
        $unwrapped = self::unwrap($source, $node);
        if ($unwrapped === null) {
            return null;
        }

        if ($unwrapped->kind === NodeKind::ClassConstantAccess) {
            $children = $source->getChildren($unwrapped);
            $classExpr = self::unwrap($source, $children[0] ?? null);
            $constSelector = $children[1] ?? null;
            $constIdent = $constSelector !== null ? ($source->getChildren($constSelector)[0] ?? null) : null;
            if (
                $classExpr !== null
                && $constIdent !== null
                && strtolower($source->getText($constIdent)) === 'class'
            ) {
                $resolved = $source->getResolvedName($classExpr)?->name;
                if ($resolved !== null && $resolved !== '') {
                    return ltrim($resolved, '\\');
                }
                $raw = ltrim($source->getText($classExpr), '\\');
                if ($raw !== '' && $raw[0] !== '$') {
                    return $raw;
                }
            }
            return null;
        }

        if ($unwrapped->kind === NodeKind::Binary) {
            $children = $source->getChildren($unwrapped);
            $op = isset($children[1]) ? trim($source->getText($children[1])) : '';
            if ($op === '.' && isset($children[0], $children[2])) {
                $lhs = self::evaluateStaticStringExpression($source, $children[0]);
                $rhs = self::evaluateStaticStringExpression($source, $children[2]);
                if ($lhs !== null && $rhs !== null) {
                    return $lhs . $rhs;
                }
            }
            return null;
        }

        return self::extractLiteralString($source, $unwrapped);
    }

    private static function findFetchArgument(SourceFile $source, Node $argList): ?Node
    {
        foreach ($source->getChildren($argList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }
            if ($variant->kind === NodeKind::PositionalArgument) {
                return $source->getChildren($variant)[0] ?? null;
            }
            if ($variant->kind === NodeKind::NamedArgument) {
                $parts = $source->getChildren($variant);
                if (isset($parts[0], $parts[1])) {
                    $label = $source->getText($parts[0]);
                    if ($label === 'name' || $label === 'reference') {
                        return $parts[1];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<Node>
     */
    private static function positionalArgumentValues(SourceFile $source, Node $argList): array
    {
        $values = [];
        foreach ($source->getChildren($argList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant !== null && $variant->kind === NodeKind::PositionalArgument) {
                $val = $source->getChildren($variant)[0] ?? null;
                if ($val !== null) {
                    $values[] = $val;
                }
            }
        }

        return $values;
    }

    private static function calledStaticMethodName(SourceFile $source, Node $call): ?string
    {
        $member = $source->getChildren($call)[1] ?? null;
        if ($member === null) {
            return null;
        }
        $selector = $source->getChildren($member)[0] ?? null;

        return $selector !== null && $selector->kind === NodeKind::LocalIdentifier
            ? $source->getText($selector)
            : null;
    }

    private static function calledInstanceMethodName(SourceFile $source, Node $call): ?string
    {
        return self::calledStaticMethodName($source, $call);
    }

    private static function calledFunctionName(SourceFile $source, Node $call): ?string
    {
        $target = self::unwrap($source, $source->getChildren($call)[0] ?? null);
        if ($target === null) {
            return null;
        }

        if (
            $target->kind === NodeKind::Identifier
            || $target->kind === NodeKind::LocalIdentifier
            || $target->kind === NodeKind::QualifiedIdentifier
            || $target->kind === NodeKind::FullyQualifiedIdentifier
        ) {
            return $source->getResolvedName($target)?->name ?? $source->getText($target);
        }

        return null;
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<array{end: int, targets: list<string>|null}>>>,
     *     lifecycle: array<string, array<string, list<string>|null>>
     * } $fileAliases
     * @return list<string>|null
     */
    private static function targetsForFetchInScope(
        SourceFile $source,
        Node $call,
        string $fetchName,
        array $fileAliases,
    ): ?array {
        $callable = self::enclosingCallable($source, $call);
        if ($callable !== null) {
            $methodKey = $callable->span->start . ':' . $callable->span->end;
            $entries = $fileAliases['methods'][$methodKey][$fetchName] ?? [];
            $matched = false;
            $resolved = null;
            foreach ($entries as $entry) {
                if ($entry['end'] > $call->span->start) {
                    continue;
                }
                if (!$matched) {
                    $matched = true;
                    $resolved = $entry['targets'];
                } elseif ($resolved !== $entry['targets']) {
                    return null;
                }
            }
            if ($matched) {
                return $resolved;
            }
        }

        $classKey = self::enclosingClassKey($source, $call);
        if ($classKey !== null && array_key_exists($fetchName, $fileAliases['lifecycle'][$classKey] ?? [])) {
            return $fileAliases['lifecycle'][$classKey][$fetchName];
        }

        return null;
    }

    private static function enclosingCallable(SourceFile $source, Node $node): ?Node
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if (
                $current->kind === NodeKind::Method
                || $current->kind === NodeKind::Function
                || $current->kind === NodeKind::Closure
                || $current->kind === NodeKind::ArrowFunction
            ) {
                return $current;
            }
            $current = $source->getParent($current);
        }

        return null;
    }

    private static function enclosingClassKey(SourceFile $source, Node $node): ?string
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if ($current->kind === NodeKind::Class_ || $current->kind === NodeKind::AnonymousClass) {
                return $current->span->start . ':' . $current->span->end;
            }
            $current = $source->getParent($current);
        }

        return null;
    }

    private static function isLifecycleCallable(SourceFile $source, Node $callable): bool
    {
        if ($callable->kind !== NodeKind::Method) {
            return false;
        }

        $body = self::findChildOfKind($source, $callable, NodeKind::MethodBody);
        $bodyStart = $body?->span->start ?? $callable->span->end;
        foreach ($source->getChildren($callable) as $child) {
            if ($child->span->start >= $bodyStart) {
                break;
            }
            if ($child->kind === NodeKind::LocalIdentifier) {
                if (isset(self::LIFECYCLE_METHODS[strtolower($source->getText($child))])) {
                    return true;
                }
            }
            if ($child->kind === NodeKind::AttributeList) {
                foreach ($source->getDescendants($child, NodeKind::Attribute) as $attribute) {
                    $nameNode = self::unwrap($source, $source->getChildren($attribute)[0] ?? null);
                    if ($nameNode === null) {
                        continue;
                    }
                    $resolved = ltrim($source->getResolvedName($nameNode)?->name ?? $source->getText($nameNode), '\\');
                    $lower = strtolower($resolved);
                    if ($lower === 'phpunit\\framework\\attributes\\before' || $lower === 'before' || str_ends_with($lower, '\\before')) {
                        return true;
                    }
                }
            }
        }

        $text = $source->getText($callable);
        $brace = strpos($text, '{');
        $header = $brace === false ? $text : substr($text, 0, $brace);
        $matches = [];
        if (preg_match('/function\s+(\w+)/i', $header, $matches) === 1) {
            if (isset(self::LIFECYCLE_METHODS[strtolower($matches[1])])) {
                return true;
            }
        }

        return false;
    }

    private static function isMockeryStaticReceiver(SourceFile $source, Node $call): bool
    {
        $classNode = self::unwrap($source, $source->getChildren($call)[0] ?? null);
        if ($classNode === null) {
            return false;
        }

        if (
            $classNode->kind === NodeKind::Identifier
            || $classNode->kind === NodeKind::LocalIdentifier
            || $classNode->kind === NodeKind::QualifiedIdentifier
            || $classNode->kind === NodeKind::FullyQualifiedIdentifier
        ) {
            $resolved = $source->getResolvedName($classNode)?->name;
            $name = ltrim($resolved ?? $source->getText($classNode), '\\');

            return strtolower($name) === 'mockery';
        }

        return false;
    }

    private static function findChildOfKind(SourceFile $source, Node $node, NodeKind $kind): ?Node
    {
        foreach ($source->getChildren($node) as $child) {
            if ($child->kind === $kind) {
                return $child;
            }
        }

        return null;
    }

    private static function extractLiteralString(SourceFile $source, Node $node): ?string
    {
        $node = self::unwrap($source, $node);
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

    private static function unwrap(SourceFile $source, ?Node $node): ?Node
    {
        while (
            $node !== null
            && (
                $node->kind === NodeKind::Expression
                || $node->kind === NodeKind::Call
                || $node->kind === NodeKind::Parenthesized
                || $node->kind === NodeKind::Variable
                || $node->kind === NodeKind::Literal
                || $node->kind === NodeKind::Access
                || $node->kind === NodeKind::Identifier
            )
        ) {
            $child = $source->getChildren($node)[0] ?? null;
            if ($child === null) {
                break;
            }
            $node = $child;
        }

        return $node;
    }
}
