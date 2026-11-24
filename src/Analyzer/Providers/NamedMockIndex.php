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
use function ltrim;
use function stripos;
use function strlen;
use function strtolower;
use function substr;

/**
 * Indexes same-file `namedMock()` targets for `fetchMock()` calls by exact call span.
 *
 * Cross-file `fetchMock()` calls remain unresolved; span collisions across files with
 * differing targets degrade conservatively to unresolved.
 */
final class NamedMockIndex implements CodebaseScanHook
{
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
                : [];

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
     * @return array<string, list<string>|null>
     */
    private static function collectFileNamedMocks(SourceFile $source): array
    {
        $aliases = [];

        foreach ($source->getNodes(NodeKind::StaticMethodCall) as $call) {
            $method = self::calledStaticMethodName($source, $call);
            if ($method === null || strtolower($method) !== 'namedmock') {
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
     * @param array<string, list<string>|null> $aliases
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
        if (array_key_exists($aliasName, $aliases)) {
            if ($aliases[$aliasName] !== $resolved) {
                $aliases[$aliasName] = null;
            }
            return;
        }

        $aliases[$aliasName] = $resolved;
    }

    /**
     * @param array<string, list<string>|null> $fileAliases
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

                $targets = $fileAliases[$fetchName] ?? null;
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
        $positionalIndex = 0;
        foreach ($source->getChildren($argList) as $argument) {
            $variant = $source->getChildren($argument)[0] ?? null;
            if ($variant === null) {
                continue;
            }
            if ($variant->kind === NodeKind::PositionalArgument) {
                if ($positionalIndex === 0) {
                    return $source->getChildren($variant)[0] ?? null;
                }
                ++$positionalIndex;
            } elseif ($variant->kind === NodeKind::NamedArgument) {
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
