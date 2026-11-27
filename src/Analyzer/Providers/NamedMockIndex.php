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
use function array_keys;
use function array_pop;
use function array_values;
use function count;
use function implode;
use function ltrim;
use function preg_match;
use function sort;
use function stripos;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Indexes `namedMock()` targets for `fetchMock()` calls by exact call span,
 * supporting enclosing callable scopes (closures/arrow functions), trait lifecycle
 * hooks (`setUp`, `#[Before]`, `startMockery`, `mockeryTestSetUp`), and branch
 * dominance before shadowing lifecycle aliases.
 *
 * @phpstan-type TargetSet non-empty-list<string>
 * @phpstan-type MethodAliasEntry array{end: int, targets: ?TargetSet, node: Node}
 * @phpstan-type FetchRecord array{
 *     localSets: list<TargetSet>,
 *     dominated: bool,
 *     unresolved: bool,
 *     usedTraits: list<string>
 * }
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

    private const CONDITIONAL_ANCESTOR_KINDS = [
        NodeKind::If->value => true,
        NodeKind::IfBody->value => true,
        NodeKind::IfStatementBody->value => true,
        NodeKind::IfStatementBodyElseClause->value => true,
        NodeKind::IfStatementBodyElseIfClause->value => true,
        NodeKind::IfColonDelimitedBody->value => true,
        NodeKind::IfColonDelimitedBodyElseClause->value => true,
        NodeKind::IfColonDelimitedBodyElseIfClause->value => true,
        NodeKind::Switch->value => true,
        NodeKind::SwitchCase->value => true,
        NodeKind::SwitchExpressionCase->value => true,
        NodeKind::SwitchDefaultCase->value => true,
        NodeKind::While->value => true,
        NodeKind::DoWhile->value => true,
        NodeKind::For->value => true,
        NodeKind::Foreach->value => true,
        NodeKind::Try->value => true,
        NodeKind::TryCatchClause->value => true,
        NodeKind::Conditional->value => true,
        NodeKind::Match->value => true,
        NodeKind::MatchArm->value => true,
        NodeKind::MatchExpressionArm->value => true,
        NodeKind::MatchDefaultArm->value => true,
        NodeKind::Closure->value => true,
        NodeKind::ArrowFunction->value => true,
    ];

    private const NON_DOMINATING_LCA_KINDS = [
        NodeKind::If->value => true,
        NodeKind::IfBody->value => true,
        NodeKind::IfStatementBody->value => true,
        NodeKind::IfColonDelimitedBody->value => true,
        NodeKind::Switch->value => true,
        NodeKind::SwitchBody->value => true,
        NodeKind::SwitchBraceDelimitedBody->value => true,
        NodeKind::SwitchColonDelimitedBody->value => true,
        NodeKind::Match->value => true,
        NodeKind::Try->value => true,
        NodeKind::Conditional->value => true,
    ];

    private const SHORT_CIRCUIT_OPERATORS = [
        '&&' => true,
        '||' => true,
        'and' => true,
        'or' => true,
        '??' => true,
    ];

    /** @var array<string, FetchRecord> */
    private array $fetchRecordsByKey = [];

    /** @var array<string, true> */
    private array $ambiguousKeys = [];

    /** @var array<string, array<string, ?TargetSet>> */
    private array $traitLifecycle = [];

    /** @var array<string, list<string>> */
    private array $traitParents = [];

    public function getTargets(): array
    {
        return ['**/*.php'];
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->fetchRecordsByKey = [];
            $this->ambiguousKeys = [];
            $this->traitLifecycle = [];
            $this->traitParents = [];
        }

        foreach ($context->files as $source) {
            $hasFetch = stripos($source->contents, 'fetchMock') !== false;
            $hasNamed = stripos($source->contents, 'namedMock') !== false;
            $hasTrait = stripos($source->contents, 'trait') !== false;

            if (!$hasFetch && !$hasNamed && !$hasTrait) {
                continue;
            }

            if ($hasTrait) {
                self::recordTraitUses($source, $this->traitParents);
            }

            $fileAliases = $hasNamed
                ? self::collectFileNamedMocks($source, $this->traitLifecycle)
                : ['methods' => [], 'lifecycle' => []];

            if ($hasFetch) {
                self::recordFileFetchCalls($source, $fileAliases, $this->fetchRecordsByKey, $this->ambiguousKeys);
            }
        }
    }

    /**
     * @return ?TargetSet
     */
    public function targetsForFetch(Span $span, string $name): ?array
    {
        $sets = $this->targetSetsForFetch($span, $name);
        if ($sets === null || count($sets) !== 1) {
            return null;
        }

        return $sets[0];
    }

    /**
     * @return ?non-empty-list<TargetSet>
     */
    public function targetSetsForFetch(Span $span, string $name): ?array
    {
        $key = $span->start . ':' . $span->end . "\0" . $name;
        if (isset($this->ambiguousKeys[$key]) || !isset($this->fetchRecordsByKey[$key])) {
            return null;
        }

        $record = $this->fetchRecordsByKey[$key];
        if ($record['unresolved']) {
            return null;
        }

        $sets = $record['localSets'];
        if (!$record['dominated'] && $record['usedTraits'] !== []) {
            $traitSets = $this->collectTraitLifecycleTargets($record['usedTraits'], $name);
            if ($traitSets === null) {
                return null;
            }
            foreach ($traitSets as $set) {
                $sets[] = $set;
            }
        }

        $unique = self::dedupeTargetSets($sets);

        return $unique !== [] ? $unique : null;
    }

    /**
     * @param list<string> $initialTraits
     * @return ?list<TargetSet> Null indicates an ambiguous/unresolvable trait lifecycle registration.
     */
    private function collectTraitLifecycleTargets(array $initialTraits, string $aliasName): ?array
    {
        $sets = [];
        $visited = [];
        $queue = $initialTraits;

        while (($traitLower = array_pop($queue)) !== null) {
            if (isset($visited[$traitLower])) {
                continue;
            }
            $visited[$traitLower] = true;

            if (isset($this->traitLifecycle[$traitLower]) && array_key_exists($aliasName, $this->traitLifecycle[$traitLower])) {
                $targets = $this->traitLifecycle[$traitLower][$aliasName];
                if ($targets === null) {
                    return null;
                }
                $sets[] = $targets;
            }

            foreach ($this->traitParents[$traitLower] ?? [] as $parentTrait) {
                if (!isset($visited[$parentTrait])) {
                    $queue[] = $parentTrait;
                }
            }
        }

        return $sets;
    }

    /**
     * @param array<string, list<string>> $traitParents
     */
    private static function recordTraitUses(SourceFile $source, array &$traitParents): void
    {
        foreach ($source->getNodes(NodeKind::Trait) as $traitNode) {
            $fqcn = self::classLikeFqcn($source, $traitNode);
            if ($fqcn === null) {
                continue;
            }

            $used = self::usedTraitsOfClassLike($source, $traitNode);
            if ($used === []) {
                continue;
            }

            $lower = strtolower($fqcn);
            $existing = $traitParents[$lower] ?? [];
            $merged = [];
            foreach ($existing as $t) {
                $merged[$t] = true;
            }
            foreach ($used as $t) {
                $merged[$t] = true;
            }
            $traitParents[$lower] = array_keys($merged);
        }
    }

    /**
     * @param array<string, array<string, ?TargetSet>> $traitLifecycle
     * @return array{
     *     methods: array<string, array<string, list<MethodAliasEntry>>>,
     *     lifecycle: array<string, array<string, ?TargetSet>>
     * }
     */
    private static function collectFileNamedMocks(SourceFile $source, array &$traitLifecycle): array
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
            self::extractNamedMockCall($source, $call, $aliases, $traitLifecycle);
        }

        foreach ($source->getNodes(NodeKind::FunctionCall) as $call) {
            $func = self::calledFunctionName($source, $call);
            if ($func === null || strtolower(ltrim($func, '\\')) !== 'namedmock') {
                continue;
            }
            self::extractNamedMockCall($source, $call, $aliases, $traitLifecycle);
        }

        return $aliases;
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<MethodAliasEntry>>>,
     *     lifecycle: array<string, array<string, ?TargetSet>>
     * } $aliases
     * @param array<string, array<string, ?TargetSet>> $traitLifecycle
     */
    private static function extractNamedMockCall(
        SourceFile $source,
        Node $call,
        array &$aliases,
        array &$traitLifecycle,
    ): void {
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
        if ($callable !== null && self::isLifecycleCallable($source, $callable)) {
            $classNode = self::enclosingClassNode($source, $call);
            if ($classNode !== null) {
                $classKey = $classNode->span->start . ':' . $classNode->span->end;
                self::recordLifecycleEntry($aliases['lifecycle'], $classKey, $aliasName, $resolved);

                if ($classNode->kind === NodeKind::Trait) {
                    $traitFqcn = self::classLikeFqcn($source, $classNode);
                    if ($traitFqcn !== null) {
                        self::recordLifecycleEntry($traitLifecycle, strtolower($traitFqcn), $aliasName, $resolved);
                    }
                }

                return;
            }
        }

        $scopeKey = $callable !== null ? $callable->span->start . ':' . $callable->span->end : '__top__';
        $aliases['methods'][$scopeKey][$aliasName][] = [
            'end' => $call->span->end,
            'targets' => $resolved,
            'node' => $call,
        ];
    }

    /**
     * @param array<string, array<string, ?TargetSet>> $table
     * @param ?TargetSet $resolved
     */
    private static function recordLifecycleEntry(array &$table, string $ownerKey, string $aliasName, ?array $resolved): void
    {
        $table[$ownerKey] ??= [];
        if (array_key_exists($aliasName, $table[$ownerKey])) {
            if ($table[$ownerKey][$aliasName] !== $resolved) {
                $table[$ownerKey][$aliasName] = null;
            }

            return;
        }

        $table[$ownerKey][$aliasName] = $resolved;
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<MethodAliasEntry>>>,
     *     lifecycle: array<string, array<string, ?TargetSet>>
     * } $fileAliases
     * @param array<string, FetchRecord> $fetchRecordsByKey
     * @param array<string, true> $ambiguousKeys
     */
    private static function recordFileFetchCalls(
        SourceFile $source,
        array $fileAliases,
        array &$fetchRecordsByKey,
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

                $record = self::buildFetchRecord($source, $call, $fetchName, $fileAliases);
                if (array_key_exists($key, $fetchRecordsByKey)) {
                    if ($fetchRecordsByKey[$key] !== $record) {
                        $ambiguousKeys[$key] = true;
                        unset($fetchRecordsByKey[$key]);
                    }
                    continue;
                }

                $fetchRecordsByKey[$key] = $record;
            }
        }
    }

    /**
     * @param array{
     *     methods: array<string, array<string, list<MethodAliasEntry>>>,
     *     lifecycle: array<string, array<string, ?TargetSet>>
     * } $fileAliases
     * @return FetchRecord
     */
    private static function buildFetchRecord(
        SourceFile $source,
        Node $call,
        string $fetchName,
        array $fileAliases,
    ): array {
        $callableChain = self::enclosingCallableChain($source, $call);
        $scopes = [];
        foreach ($callableChain as $callable) {
            $scopes[] = [
                'key' => $callable->span->start . ':' . $callable->span->end,
                'node' => $callable,
            ];
        }
        $outermost = $callableChain !== [] ? $callableChain[count($callableChain) - 1] : null;
        if ($outermost === null || ($outermost->kind !== NodeKind::Method && $outermost->kind !== NodeKind::Function)) {
            $scopes[] = ['key' => '__top__', 'node' => null];
        }

        $localSets = [];
        $dominated = false;
        $unresolved = false;

        foreach ($scopes as $scope) {
            $entries = $fileAliases['methods'][$scope['key']][$fetchName] ?? [];
            if ($entries === []) {
                continue;
            }

            /** @var ?MethodAliasEntry $latestDominating */
            $latestDominating = null;
            /** @var list<MethodAliasEntry> $conditionals */
            $conditionals = [];

            foreach ($entries as $entry) {
                if ($entry['end'] > $call->span->start) {
                    continue;
                }

                if (self::entryDominatesCall($source, $entry['node'], $call, $scope['node'])) {
                    $latestDominating = $entry;
                    $conditionals = [];
                } else {
                    $conditionals[] = $entry;
                }
            }

            foreach ($conditionals as $cond) {
                if ($cond['targets'] === null) {
                    $unresolved = true;
                } else {
                    $localSets[] = $cond['targets'];
                }
            }

            if ($latestDominating !== null) {
                if ($latestDominating['targets'] === null) {
                    $unresolved = true;
                } else {
                    $localSets[] = $latestDominating['targets'];
                }
                $dominated = true;
                break;
            }
        }

        $usedTraits = [];
        if (!$dominated) {
            $classNode = self::enclosingClassNode($source, $call);
            if ($classNode !== null) {
                $classKey = $classNode->span->start . ':' . $classNode->span->end;
                if (array_key_exists($fetchName, $fileAliases['lifecycle'][$classKey] ?? [])) {
                    $lifecycleTargets = $fileAliases['lifecycle'][$classKey][$fetchName];
                    if ($lifecycleTargets === null) {
                        $unresolved = true;
                    } else {
                        $localSets[] = $lifecycleTargets;
                    }
                }
                $usedTraits = self::usedTraitsOfClassLike($source, $classNode);
            }
        }

        sort($usedTraits);

        return [
            'localSets' => self::dedupeTargetSets($localSets),
            'dominated' => $dominated,
            'unresolved' => $unresolved,
            'usedTraits' => $usedTraits,
        ];
    }

    private static function entryDominatesCall(SourceFile $source, Node $entryNode, Node $call, ?Node $scopeNode): bool
    {
        if ($entryNode->span->end > $call->span->start) {
            return false;
        }

        $current = $entryNode;
        while ($current !== null) {
            $parent = $source->getParent($current);
            if ($parent === null) {
                return true;
            }

            if ($parent->kind === NodeKind::Binary) {
                $children = $source->getChildren($parent);
                $op = isset($children[1]) ? strtolower(trim($source->getText($children[1]))) : '';
                if (isset(self::SHORT_CIRCUIT_OPERATORS[$op]) && isset($children[2]) && $children[2]->id === $current->id) {
                    return false;
                }
            }

            if ($parent->span->start <= $call->span->start && $parent->span->end >= $call->span->end) {
                return !isset(self::NON_DOMINATING_LCA_KINDS[$parent->kind->value]);
            }

            if (isset(self::CONDITIONAL_ANCESTOR_KINDS[$parent->kind->value])) {
                return false;
            }

            if ($scopeNode !== null && $parent->id === $scopeNode->id) {
                return true;
            }

            $current = $parent;
        }

        return true;
    }

    /**
     * @param list<TargetSet> $sets
     * @return list<TargetSet>
     */
    private static function dedupeTargetSets(array $sets): array
    {
        $unique = [];
        foreach ($sets as $set) {
            $normalized = [];
            foreach ($set as $name) {
                $normalized[] = strtolower($name);
            }
            sort($normalized);
            $sig = implode("\0", $normalized);
            if (!isset($unique[$sig])) {
                $unique[$sig] = $set;
            }
        }

        return array_values($unique);
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

    private static function enclosingCallable(SourceFile $source, Node $node): ?Node
    {
        $chain = self::enclosingCallableChain($source, $node);

        return $chain[0] ?? null;
    }

    /**
     * Returns enclosing callables from innermost to outermost, walking out of closures
     * and arrow functions into their enclosing method or function.
     *
     * @return list<Node>
     */
    private static function enclosingCallableChain(SourceFile $source, Node $node): array
    {
        $chain = [];
        $current = $source->getParent($node);
        while ($current !== null) {
            if ($current->kind === NodeKind::Closure || $current->kind === NodeKind::ArrowFunction) {
                $chain[] = $current;
            } elseif ($current->kind === NodeKind::Method || $current->kind === NodeKind::Function) {
                $chain[] = $current;
                break;
            } elseif (
                $current->kind === NodeKind::Class_
                || $current->kind === NodeKind::AnonymousClass
                || $current->kind === NodeKind::Trait
            ) {
                break;
            }
            $current = $source->getParent($current);
        }

        return $chain;
    }

    private static function enclosingClassNode(SourceFile $source, Node $node): ?Node
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if (
                $current->kind === NodeKind::Class_
                || $current->kind === NodeKind::AnonymousClass
                || $current->kind === NodeKind::Trait
            ) {
                return $current;
            }
            $current = $source->getParent($current);
        }

        return null;
    }

    private static function classLikeFqcn(SourceFile $source, Node $classLike): ?string
    {
        $resolved = $source->getResolvedName($classLike)?->name;
        if ($resolved !== null && $resolved !== '') {
            return ltrim($resolved, '\\');
        }

        foreach ($source->getChildren($classLike) as $child) {
            if ($child->kind === NodeKind::LocalIdentifier) {
                $childResolved = $source->getResolvedName($child)?->name;
                if ($childResolved !== null && $childResolved !== '') {
                    return ltrim($childResolved, '\\');
                }

                $shortName = trim($source->getText($child));
                if ($shortName === '') {
                    return null;
                }

                $ns = self::enclosingNamespaceName($source, $classLike);

                return $ns !== '' ? $ns . '\\' . $shortName : $shortName;
            }
        }

        return null;
    }

    private static function enclosingNamespaceName(SourceFile $source, Node $node): string
    {
        $current = $source->getParent($node);
        while ($current !== null) {
            if ($current->kind->value === 'Namespace') {
                foreach ($source->getChildren($current) as $child) {
                    $ident = self::unwrap($source, $child);
                    if (
                        $ident !== null
                        && (
                            $ident->kind === NodeKind::LocalIdentifier
                            || $ident->kind === NodeKind::QualifiedIdentifier
                            || $ident->kind === NodeKind::FullyQualifiedIdentifier
                        )
                    ) {
                        return ltrim(trim($source->getText($ident)), '\\');
                    }
                }

                return '';
            }
            $current = $source->getParent($current);
        }

        return '';
    }

    /**
     * @return list<string> Lowercased FQCNs of traits directly used by $classLike.
     */
    private static function usedTraitsOfClassLike(SourceFile $source, Node $classLike): array
    {
        $used = [];
        foreach ($source->getDescendants($classLike, NodeKind::TraitUse) as $traitUse) {
            if (self::enclosingClassNode($source, $traitUse)?->id !== $classLike->id) {
                continue;
            }

            foreach ($source->getChildren($traitUse) as $child) {
                if (
                    $child->kind === NodeKind::TraitUseSpecification
                    || $child->kind === NodeKind::TraitUseAbstractSpecification
                    || $child->kind === NodeKind::TraitUseConcreteSpecification
                ) {
                    continue;
                }

                $ident = self::unwrap($source, $child);
                if (
                    $ident === null
                    || (
                        $ident->kind !== NodeKind::LocalIdentifier
                        && $ident->kind !== NodeKind::QualifiedIdentifier
                        && $ident->kind !== NodeKind::FullyQualifiedIdentifier
                    )
                ) {
                    continue;
                }

                $resolved = $source->getResolvedName($ident)?->name ?? $source->getResolvedName($child)?->name;
                if ($resolved === null || $resolved === '') {
                    $raw = ltrim(trim($source->getText($ident)), '\\');
                    if ($raw === '') {
                        continue;
                    }
                    if ($ident->kind !== NodeKind::FullyQualifiedIdentifier) {
                        $ns = self::enclosingNamespaceName($source, $classLike);
                        $resolved = $ns !== '' ? $ns . '\\' . $raw : $raw;
                    } else {
                        $resolved = $raw;
                    }
                }

                $used[strtolower(ltrim($resolved, '\\'))] = true;
            }
        }

        return array_keys($used);
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
