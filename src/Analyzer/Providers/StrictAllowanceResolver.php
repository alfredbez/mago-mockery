<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_intersect_key;
use function array_keys;
use function array_pop;
use function count;
use function ltrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function preg_match;
use function preg_replace;
use function strpos;
use function strtolower;
use function substr;

/**
 * Intra-procedural value-numbering and dominance-lite tracker for `shouldAllowMockingMethod()`.
 *
 * Tracks receiver identity across variable aliases and kills stale allowances on reassignment
 * or across control-flow splits where the allowance does not dominate the call site.
 *
 * @internal
 */
final class StrictAllowanceResolver
{
    private const FLUENT_PRESERVING_METHODS = [
        'asundefined' => true,
        'bydefault' => true,
        'makepartial' => true,
        'shouldallowmockingmethod' => true,
        'shouldallowmockingprotectedmethods' => true,
        'shoulddefermissing' => true,
        'shouldignoremissing' => true,
    ];

    private const CONDITIONAL_BINARY_OPERATORS = [
        '&&' => true,
        '||' => true,
        'and' => true,
        'or' => true,
        '??' => true,
    ];

    private function __construct() {}

    public static function isAllowed(SourceFile $source, Node $call, string $wantedLower): bool
    {
        if (self::receiverChainAllowsMethod($source, $call, $wantedLower)) {
            return true;
        }

        $receiverVar = self::baseReceiverVariableName($source, $call);
        if ($receiverVar === null) {
            return false;
        }

        $scope = self::enclosingScope($source, $call);
        $statements = self::scopeStatements($source, $scope);
        if ($statements === []) {
            return false;
        }

        $nextId = 1;
        $state = [
            'vars' => [],
            'allowed' => [],
            'refs' => [],
            'terminated' => false,
        ];

        self::walkStatements($source, $statements, $call->span->start, $state, $nextId);

        $id = self::varId($state, $receiverVar);

        return isset($state['allowed'][$id][$wantedLower]);
    }

    public static function receiverChainAllowsMethod(SourceFile $source, Node $call, string $wantedLower): bool
    {
        $receiver = self::unwrap($source, $source->getChildren($call)[0] ?? null);
        while ($receiver !== null && $receiver->kind === NodeKind::MethodCall) {
            $allowed = self::extractAllowedMethodName($source, $receiver);
            if ($allowed !== null && strtolower($allowed) === $wantedLower) {
                return true;
            }

            $receiver = self::unwrap($source, $source->getChildren($receiver)[0] ?? null);
        }

        return false;
    }

    public static function baseReceiverVariableName(SourceFile $source, Node $call): ?string
    {
        $current = self::unwrap($source, $source->getChildren($call)[0] ?? null);
        while ($current !== null && $current->kind === NodeKind::MethodCall) {
            $current = self::unwrap($source, $source->getChildren($current)[0] ?? null);
        }

        if ($current === null) {
            return null;
        }

        return self::identityKey($source, $current);
    }

    private static function identityKey(SourceFile $source, Node $node): ?string
    {
        $unwrapped = self::unwrap($source, $node);
        if ($unwrapped === null) {
            return null;
        }

        if ($unwrapped->kind === NodeKind::DirectVariable) {
            return $source->getText($unwrapped);
        }

        $text = preg_replace('/\s+/', '', $source->getText($unwrapped)) ?? '';
        if (preg_match('/^\$this->([A-Za-z_][A-Za-z0-9_]*)$/', $text) === 1) {
            return $text;
        }

        return null;
    }

    /**
     * @param list<Node> $statements
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     * @return bool True when traversal reached the target call offset and must stop.
     */
    private static function walkStatements(
        SourceFile $source,
        array $statements,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        foreach ($statements as $statement) {
            if ($statement->span->start > $targetStart) {
                return true;
            }

            $unwrapped = self::unwrapStatement($source, $statement);
            if ($unwrapped === null || self::isScopeBoundary($unwrapped->kind)) {
                continue;
            }

            if ($statement->span->end > $targetStart) {
                return self::walkContainingStatement($source, $unwrapped, $targetStart, $state, $nextId);
            }

            self::processStatement($source, $unwrapped, $state, $nextId);
        }

        return false;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkContainingStatement(
        SourceFile $source,
        Node $statement,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        if ($statement->kind->value === 'Namespace') {
            return self::walkStatements($source, self::namespaceStatements($source, $statement), $targetStart, $state, $nextId);
        }

        return match ($statement->kind) {
            NodeKind::Block => self::walkStatements($source, $source->getChildren($statement), $targetStart, $state, $nextId),
            NodeKind::If => self::walkContainingIf($source, $statement, $targetStart, $state, $nextId),
            NodeKind::While, NodeKind::DoWhile, NodeKind::For, NodeKind::Foreach => self::walkContainingLoop($source, $statement, $targetStart, $state, $nextId),
            NodeKind::Try => self::walkContainingTry($source, $statement, $targetStart, $state, $nextId),
            NodeKind::Switch => self::walkContainingSwitch($source, $statement, $targetStart, $state, $nextId),
            default => self::walkExpressionUntil($source, $statement, $targetStart, $state, $nextId),
        };
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processStatement(
        SourceFile $source,
        Node $statement,
        array &$state,
        int &$nextId,
    ): void {
        if ($state['terminated']) {
            return;
        }

        if ($statement->kind->value === 'Namespace') {
            foreach (self::namespaceStatements($source, $statement) as $child) {
                $inner = self::unwrapStatement($source, $child);
                if ($inner !== null && !self::isScopeBoundary($inner->kind)) {
                    self::processStatement($source, $inner, $state, $nextId);
                }
            }
            return;
        }

        switch ($statement->kind) {
            case NodeKind::Block:
                foreach ($source->getChildren($statement) as $child) {
                    $inner = self::unwrapStatement($source, $child);
                    if ($inner !== null && !self::isScopeBoundary($inner->kind)) {
                        self::processStatement($source, $inner, $state, $nextId);
                    }
                }
                return;

            case NodeKind::ExpressionStatement:
                foreach ($source->getChildren($statement) as $child) {
                    if ($child->kind !== NodeKind::Terminator) {
                        self::processExpression($source, $child, $state, $nextId, allowGrants: true);
                    }
                }
                return;

            case NodeKind::If:
                self::processIf($source, $statement, $state, $nextId);
                return;

            case NodeKind::While:
            case NodeKind::DoWhile:
            case NodeKind::For:
            case NodeKind::Foreach:
            case NodeKind::Switch:
                self::killWrittenVariables($source, $statement, $state, $nextId);
                return;

            case NodeKind::Try:
                self::processTry($source, $statement, $state, $nextId);
                return;

            case NodeKind::Unset:
                foreach ($source->getChildren($statement) as $child) {
                    $key = self::identityKey($source, $child);
                    if ($key !== null) {
                        self::detachRef($state, $key);
                        $state['vars'][$key] = self::freshId($nextId);
                    }
                }
                foreach ($source->getDescendants($statement, NodeKind::DirectVariable) as $var) {
                    if (!self::isInsideNestedScope($source, $statement, $var)) {
                        $varName = $source->getText($var);
                        self::detachRef($state, $varName);
                        $state['vars'][$varName] = self::freshId($nextId);
                    }
                }
                return;

            case NodeKind::Return:
            case NodeKind::Throw:
            case NodeKind::Break:
            case NodeKind::Continue:
                self::killWrittenVariables($source, $statement, $state, $nextId);
                $state['terminated'] = true;
                return;

            default:
                if (self::containsExitOrDie($source, $statement)) {
                    self::killWrittenVariables($source, $statement, $state, $nextId);
                    $state['terminated'] = true;
                    return;
                }
                self::killWrittenVariables($source, $statement, $state, $nextId);
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processIf(SourceFile $source, Node $ifNode, array &$state, int &$nextId): void
    {
        $parts = self::extractIfParts($source, $ifNode);
        if ($parts['condition'] !== null) {
            self::processExpression($source, $parts['condition'], $state, $nextId, allowGrants: false);
        }

        $branchStates = [];

        $thenState = $state;
        self::processStatementList($source, $parts['then'], $thenState, $nextId);
        $branchStates[] = $thenState;

        $condCarry = $state;
        foreach ($parts['elseifs'] as $elseIf) {
            if ($elseIf['condition'] !== null) {
                self::processExpression($source, $elseIf['condition'], $condCarry, $nextId, allowGrants: false);
            }
            $branchState = $condCarry;
            self::processStatementList($source, $elseIf['statements'], $branchState, $nextId);
            $branchStates[] = $branchState;
        }

        if ($parts['else'] !== null) {
            $elseState = $condCarry;
            self::processStatementList($source, $parts['else'], $elseState, $nextId);
            $branchStates[] = $elseState;
        } else {
            $branchStates[] = $condCarry;
        }

        $state = self::joinStates($branchStates, $nextId);
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkContainingIf(
        SourceFile $source,
        Node $ifNode,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        $parts = self::extractIfParts($source, $ifNode);
        if ($parts['condition'] !== null) {
            if ($parts['condition']->span->start <= $targetStart && $parts['condition']->span->end > $targetStart) {
                return self::walkExpressionUntil($source, $parts['condition'], $targetStart, $state, $nextId);
            }
            self::processExpression($source, $parts['condition'], $state, $nextId, allowGrants: false);
        }

        if (self::statementsContainOffset($parts['then'], $targetStart)) {
            return self::walkStatements($source, $parts['then'], $targetStart, $state, $nextId);
        }

        foreach ($parts['elseifs'] as $elseIf) {
            if ($elseIf['condition'] !== null) {
                if ($elseIf['condition']->span->start <= $targetStart && $elseIf['condition']->span->end > $targetStart) {
                    return self::walkExpressionUntil($source, $elseIf['condition'], $targetStart, $state, $nextId);
                }
                self::processExpression($source, $elseIf['condition'], $state, $nextId, allowGrants: false);
            }
            if (self::statementsContainOffset($elseIf['statements'], $targetStart)) {
                return self::walkStatements($source, $elseIf['statements'], $targetStart, $state, $nextId);
            }
        }

        if ($parts['else'] !== null && self::statementsContainOffset($parts['else'], $targetStart)) {
            return self::walkStatements($source, $parts['else'], $targetStart, $state, $nextId);
        }

        return true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkContainingLoop(
        SourceFile $source,
        Node $loopNode,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        // Kill variables written anywhere in the loop before entering so a back-edge reassignment
        // cannot inherit a pre-loop allowance.
        self::killWrittenVariables($source, $loopNode, $state, $nextId);

        $bodyStatements = self::extractLoopBodyStatements($source, $loopNode);
        if ($bodyStatements !== [] && self::statementsContainOffset($bodyStatements, $targetStart)) {
            return self::walkStatements($source, $bodyStatements, $targetStart, $state, $nextId);
        }

        return true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processTry(SourceFile $source, Node $tryNode, array &$state, int &$nextId): void
    {
        $parts = self::extractTryParts($source, $tryNode);
        $entryState = $state;

        $tryState = $entryState;
        if ($parts['try'] !== null) {
            self::processStatementList($source, $source->getChildren($parts['try']), $tryState, $nextId);
        }

        $branchStates = [$tryState];
        foreach ($parts['catches'] as $catchNode) {
            $catchState = $entryState;
            if ($parts['try'] !== null) {
                self::killWrittenVariables($source, $parts['try'], $catchState, $nextId);
            }
            self::killCatchVariable($source, $catchNode, $catchState, $nextId);
            $catchBlock = self::firstChildOfKind($source, $catchNode, NodeKind::Block);
            if ($catchBlock !== null) {
                self::processStatementList($source, $source->getChildren($catchBlock), $catchState, $nextId);
            }
            $branchStates[] = $catchState;
        }

        $state = self::joinStates($branchStates, $nextId);

        if ($parts['finally'] !== null) {
            $finallyBlock = self::firstChildOfKind($source, $parts['finally'], NodeKind::Block);
            if ($finallyBlock !== null) {
                $wasTerminated = $state['terminated'];
                $state['terminated'] = false;
                self::processStatementList($source, $source->getChildren($finallyBlock), $state, $nextId);
                if ($wasTerminated) {
                    $state['terminated'] = true;
                }
            }
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkContainingTry(
        SourceFile $source,
        Node $tryNode,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        $parts = self::extractTryParts($source, $tryNode);
        if ($parts['try'] !== null && $parts['try']->span->start <= $targetStart && $parts['try']->span->end > $targetStart) {
            return self::walkStatements($source, $source->getChildren($parts['try']), $targetStart, $state, $nextId);
        }

        foreach ($parts['catches'] as $catchNode) {
            if ($catchNode->span->start <= $targetStart && $catchNode->span->end > $targetStart) {
                if ($parts['try'] !== null) {
                    self::killWrittenVariables($source, $parts['try'], $state, $nextId);
                }
                self::killCatchVariable($source, $catchNode, $state, $nextId);
                $catchBlock = self::firstChildOfKind($source, $catchNode, NodeKind::Block);
                if ($catchBlock !== null) {
                    return self::walkStatements($source, $source->getChildren($catchBlock), $targetStart, $state, $nextId);
                }
                return true;
            }
        }

        if ($parts['finally'] !== null && $parts['finally']->span->start <= $targetStart && $parts['finally']->span->end > $targetStart) {
            if ($parts['try'] !== null) {
                self::killWrittenVariables($source, $parts['try'], $state, $nextId);
            }
            foreach ($parts['catches'] as $catchNode) {
                self::killWrittenVariables($source, $catchNode, $state, $nextId);
            }
            $finallyBlock = self::firstChildOfKind($source, $parts['finally'], NodeKind::Block);
            if ($finallyBlock !== null) {
                return self::walkStatements($source, $source->getChildren($finallyBlock), $targetStart, $state, $nextId);
            }
        }

        return true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkContainingSwitch(
        SourceFile $source,
        Node $switchNode,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        self::killWrittenVariables($source, $switchNode, $state, $nextId);
        foreach ($source->getDescendants($switchNode, NodeKind::SwitchCase) as $caseWrapper) {
            if ($caseWrapper->span->start > $targetStart || $caseWrapper->span->end <= $targetStart) {
                continue;
            }
            $caseNode = $source->getChildren($caseWrapper)[0] ?? null;
            if ($caseNode === null) {
                continue;
            }
            $caseStatements = [];
            foreach ($source->getChildren($caseNode) as $child) {
                if ($child->kind === NodeKind::Statement) {
                    $caseStatements[] = $child;
                }
            }
            if ($caseStatements !== []) {
                return self::walkStatements($source, $caseStatements, $targetStart, $state, $nextId);
            }
        }

        return true;
    }

    /**
     * @param list<Node> $statements
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processStatementList(SourceFile $source, array $statements, array &$state, int &$nextId): void
    {
        foreach ($statements as $statement) {
            $unwrapped = self::unwrapStatement($source, $statement);
            if ($unwrapped !== null && !self::isScopeBoundary($unwrapped->kind)) {
                self::processStatement($source, $unwrapped, $state, $nextId);
            }
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function walkExpressionUntil(
        SourceFile $source,
        Node $node,
        int $targetStart,
        array &$state,
        int &$nextId,
    ): bool {
        if ($node->span->start >= $targetStart || self::isScopeBoundary($node->kind)) {
            return true;
        }

        if ($node->span->end <= $targetStart) {
            self::processExpression($source, $node, $state, $nextId, allowGrants: false);
            return false;
        }

        foreach ($source->getChildren($node) as $child) {
            if ($child->span->start >= $targetStart) {
                return true;
            }
            if ($child->span->end <= $targetStart) {
                self::processExpression($source, $child, $state, $nextId, allowGrants: false);
                continue;
            }
            if (self::walkExpressionUntil($source, $child, $targetStart, $state, $nextId)) {
                return true;
            }
        }

        return true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processExpression(
        SourceFile $source,
        Node $node,
        array &$state,
        int &$nextId,
        bool $allowGrants,
    ): void {
        $unwrapped = self::unwrap($source, $node);
        if ($unwrapped === null || self::isScopeBoundary($unwrapped->kind)) {
            return;
        }

        if ($unwrapped->kind === NodeKind::Assignment) {
            self::processAssignment($source, $unwrapped, $state, $nextId, $allowGrants);
            return;
        }

        if ($unwrapped->kind === NodeKind::MethodCall) {
            self::processMethodCallExpression($source, $unwrapped, $state, $nextId, $allowGrants);
            return;
        }

        if ($unwrapped->kind === NodeKind::Conditional || $unwrapped->kind === NodeKind::Match) {
            self::killWrittenVariables($source, $unwrapped, $state, $nextId);
            return;
        }

        if ($unwrapped->kind === NodeKind::Binary) {
            $children = $source->getChildren($unwrapped);
            $opNode = $children[1] ?? null;
            $op = $opNode !== null ? strtolower(trim($source->getText($opNode))) : '';
            if (isset(self::CONDITIONAL_BINARY_OPERATORS[$op])) {
                if (isset($children[0])) {
                    self::processExpression($source, $children[0], $state, $nextId, $allowGrants);
                }
                if (isset($children[2])) {
                    self::killWrittenVariables($source, $children[2], $state, $nextId);
                }
                return;
            }
        }

        if ($unwrapped->kind === NodeKind::ExitConstruct || $unwrapped->kind === NodeKind::DieConstruct) {
            $state['terminated'] = true;
            return;
        }

        foreach ($source->getChildren($unwrapped) as $child) {
            self::processExpression($source, $child, $state, $nextId, $allowGrants);
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processAssignment(
        SourceFile $source,
        Node $assignment,
        array &$state,
        int &$nextId,
        bool $allowGrants,
    ): void {
        $children = $source->getChildren($assignment);
        $lhsNode = $children[0] ?? null;
        $opNode = $children[1] ?? null;
        $rhsNode = $children !== [] ? $children[count($children) - 1] : null;
        $op = $opNode !== null ? trim($source->getText($opNode)) : '';

        if ($rhsNode !== null) {
            self::processExpression($source, $rhsNode, $state, $nextId, $allowGrants);
        }

        $lhsUnwrapped = self::unwrap($source, $lhsNode);
        if ($lhsUnwrapped === null) {
            return;
        }

        $lhsVar = self::identityKey($source, $lhsUnwrapped);
        if ($lhsVar === null) {
            self::killWrittenVariables($source, $lhsUnwrapped, $state, $nextId);
            return;
        }
        $isByRef = self::isByReferenceAssignment($source, $assignment, $op, $rhsNode);
        if ($isByRef && $rhsNode !== null) {
            self::detachRef($state, $lhsVar);
            $refTarget = self::extractReferenceTargetVar($source, $rhsNode);
            if ($refTarget !== null) {
                self::linkRef($state, $lhsVar, $refTarget);
                $state['vars'][$lhsVar] = self::varId($state, $refTarget);
            } else {
                $state['vars'][$lhsVar] = self::freshId($nextId);
            }
            return;
        }

        if ($op !== '=' || $rhsNode === null) {
            self::killVar($state, $lhsVar, $nextId);
            return;
        }

        $sourceVar = self::resolveIdentityPreservingRhsVar($source, $rhsNode);
        if ($sourceVar !== null) {
            self::assignVarId($state, $lhsVar, self::varId($state, $sourceVar));
            return;
        }

        self::killVar($state, $lhsVar, $nextId);
    }

    private static function isByReferenceAssignment(SourceFile $source, Node $assignment, string $op, ?Node $rhsNode): bool
    {
        if (str_contains($op, '&')) {
            return true;
        }

        if ($rhsNode !== null && str_starts_with(ltrim($source->getText($rhsNode)), '&')) {
            return true;
        }

        $text = $source->getText($assignment);
        $eqPos = strpos($text, '=');
        if ($eqPos !== false && str_starts_with(ltrim(substr($text, $eqPos + 1)), '&')) {
            return true;
        }

        return false;
    }

    private static function extractReferenceTargetVar(SourceFile $source, Node $rhsNode): ?string
    {
        $node = self::unwrap($source, $rhsNode);
        if ($node === null) {
            return null;
        }

        if ($node->kind === NodeKind::DirectVariable) {
            return $source->getText($node);
        }

        foreach ($source->getChildren($node) as $child) {
            $inner = self::unwrap($source, $child);
            if ($inner !== null && $inner->kind === NodeKind::DirectVariable) {
                return $source->getText($inner);
            }
        }

        return null;
    }

    private static function resolveIdentityPreservingRhsVar(SourceFile $source, Node $rhsNode): ?string
    {
        $rhs = self::unwrap($source, $rhsNode);
        if ($rhs === null) {
            return null;
        }

        $direct = self::identityKey($source, $rhs);
        if ($direct !== null) {
            return $direct;
        }

        if ($rhs->kind === NodeKind::Assignment) {
            $lhs = self::unwrap($source, $source->getChildren($rhs)[0] ?? null);

            return $lhs !== null ? self::identityKey($source, $lhs) : null;
        }

        $current = $rhs;
        while ($current !== null && $current->kind === NodeKind::MethodCall) {
            $methodName = self::calledMethodName($source, $current);
            if ($methodName === null || !isset(self::FLUENT_PRESERVING_METHODS[strtolower($methodName)])) {
                return null;
            }
            $current = self::unwrap($source, $source->getChildren($current)[0] ?? null);
        }

        return $current !== null ? self::identityKey($source, $current) : null;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function processMethodCallExpression(
        SourceFile $source,
        Node $call,
        array &$state,
        int &$nextId,
        bool $allowGrants,
    ): void {
        $children = $source->getChildren($call);
        if (isset($children[0])) {
            self::processExpression($source, $children[0], $state, $nextId, $allowGrants);
        }
        if (isset($children[2])) {
            self::processExpression($source, $children[2], $state, $nextId, $allowGrants);
        }

        if (!$allowGrants) {
            return;
        }

        $allowedMethod = self::extractAllowedMethodName($source, $call);
        if ($allowedMethod === null) {
            return;
        }

        $receiverVar = self::baseReceiverVariableName($source, $call);
        if ($receiverVar === null) {
            return;
        }

        $id = self::varId($state, $receiverVar);
        $state['allowed'][$id][strtolower($allowedMethod)] = true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function killWrittenVariables(
        SourceFile $source,
        Node $subtree,
        array &$state,
        int &$nextId,
    ): void {
        foreach ($source->getDescendants($subtree, NodeKind::Assignment) as $assignment) {
            if (self::isInsideNestedScope($source, $subtree, $assignment)) {
                continue;
            }
            $children = $source->getChildren($assignment);
            $lhs = $children[0] ?? null;
            $opNode = $children[1] ?? null;
            $rhs = $children !== [] ? $children[count($children) - 1] : null;
            $op = $opNode !== null ? trim($source->getText($opNode)) : '';
            if ($lhs === null) {
                continue;
            }
            $lhsUnwrapped = self::unwrap($source, $lhs);
            $lhsVar = $lhsUnwrapped !== null ? self::identityKey($source, $lhsUnwrapped) : null;
            if ($lhsVar !== null) {
                if (self::isByReferenceAssignment($source, $assignment, $op, $rhs) && $rhs !== null) {
                    $refTarget = self::extractReferenceTargetVar($source, $rhs);
                    if ($refTarget !== null) {
                        self::linkRef($state, $lhsVar, $refTarget);
                    }
                }
                self::killVar($state, $lhsVar, $nextId);
                continue;
            }
            foreach ($source->getDescendants($lhs, NodeKind::DirectVariable) as $var) {
                if (!self::isInsideNestedScope($source, $subtree, $var)) {
                    self::killVar($state, $source->getText($var), $nextId);
                }
            }
        }

        foreach ($source->getDescendants($subtree, NodeKind::Unset) as $unsetNode) {
            if (self::isInsideNestedScope($source, $subtree, $unsetNode)) {
                continue;
            }
            foreach ($source->getChildren($unsetNode) as $child) {
                $key = self::identityKey($source, $child);
                if ($key !== null) {
                    self::killVar($state, $key, $nextId);
                }
            }
            foreach ($source->getDescendants($unsetNode, NodeKind::DirectVariable) as $var) {
                if (!self::isInsideNestedScope($source, $subtree, $var)) {
                    self::killVar($state, $source->getText($var), $nextId);
                }
            }
        }

        foreach ($source->getDescendants($subtree, NodeKind::ForeachTarget) as $target) {
            if (self::isInsideNestedScope($source, $subtree, $target)) {
                continue;
            }
            foreach ($source->getDescendants($target, NodeKind::DirectVariable) as $var) {
                if (!self::isInsideNestedScope($source, $subtree, $var)) {
                    self::killVar($state, $source->getText($var), $nextId);
                }
            }
        }

        foreach ($source->getDescendants($subtree, NodeKind::TryCatchClause) as $catchNode) {
            if (self::isInsideNestedScope($source, $subtree, $catchNode)) {
                continue;
            }
            self::killCatchVariable($source, $catchNode, $state, $nextId);
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function killCatchVariable(SourceFile $source, Node $catchNode, array &$state, int &$nextId): void
    {
        foreach ($source->getChildren($catchNode) as $child) {
            if ($child->kind === NodeKind::DirectVariable) {
                self::killVar($state, $source->getText($child), $nextId);
                return;
            }
        }
    }

    /**
     * @param list<array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool}> $branches
     * @return array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool}
     */
    private static function joinStates(array $branches, int &$nextId): array
    {
        $live = [];
        foreach ($branches as $branch) {
            if (!$branch['terminated']) {
                $live[] = $branch;
            }
        }

        if ($live === []) {
            return ['vars' => [], 'allowed' => [], 'refs' => [], 'terminated' => true];
        }

        if (count($live) === 1) {
            return $live[0];
        }

        $allVars = [];
        foreach ($live as $branch) {
            foreach (array_keys($branch['vars']) as $varName) {
                $allVars[$varName] = true;
            }
        }

        $joinedVars = [];
        foreach (array_keys($allVars) as $varName) {
            $firstId = self::varId($live[0], $varName);
            $same = true;
            for ($i = 1, $total = count($live); $i < $total; ++$i) {
                if (self::varId($live[$i], $varName) !== $firstId) {
                    $same = false;
                    break;
                }
            }
            $joinedVars[$varName] = $same ? $firstId : self::freshId($nextId);
        }

        $joinedAllowed = [];
        foreach ($live[0]['allowed'] as $id => $methods) {
            $common = $methods;
            for ($i = 1, $total = count($live); $i < $total; ++$i) {
                if (!isset($live[$i]['allowed'][$id])) {
                    $common = [];
                    break;
                }
                $common = array_intersect_key($common, $live[$i]['allowed'][$id]);
                if ($common === []) {
                    break;
                }
            }
            if ($common !== []) {
                $joinedAllowed[$id] = $common;
            }
        }

        $joinedRefs = [];
        foreach ($live as $branch) {
            foreach ($branch['refs'] as $from => $targets) {
                foreach ($targets as $to => $_) {
                    $joinedRefs[$from][$to] = true;
                }
            }
        }

        return [
            'vars' => $joinedVars,
            'allowed' => $joinedAllowed,
            'refs' => $joinedRefs,
            'terminated' => false,
        ];
    }

    /**
     * @return array{condition: ?Node, then: list<Node>, elseifs: list<array{condition: ?Node, statements: list<Node>}>, else: ?list<Node>}
     */
    private static function extractIfParts(SourceFile $source, Node $ifNode): array
    {
        $condition = null;
        $ifBody = null;
        foreach ($source->getChildren($ifNode) as $child) {
            if ($child->kind === NodeKind::Expression && $condition === null) {
                $condition = $child;
            } elseif ($child->kind === NodeKind::IfBody) {
                $ifBody = $child;
            }
        }

        $then = [];
        $elseifs = [];
        $else = null;

        $bodyVariant = $ifBody !== null ? ($source->getChildren($ifBody)[0] ?? null) : null;
        if ($bodyVariant !== null) {
            foreach ($source->getChildren($bodyVariant) as $child) {
                if ($child->kind === NodeKind::Statement) {
                    $then[] = $child;
                } elseif (
                    $child->kind === NodeKind::IfStatementBodyElseIfClause
                    || $child->kind === NodeKind::IfColonDelimitedBodyElseIfClause
                ) {
                    $eiCond = null;
                    $eiStmts = [];
                    foreach ($source->getChildren($child) as $eiChild) {
                        if ($eiChild->kind === NodeKind::Expression && $eiCond === null) {
                            $eiCond = $eiChild;
                        } elseif ($eiChild->kind === NodeKind::Statement) {
                            $eiStmts[] = $eiChild;
                        }
                    }
                    $elseifs[] = ['condition' => $eiCond, 'statements' => $eiStmts];
                } elseif (
                    $child->kind === NodeKind::IfStatementBodyElseClause
                    || $child->kind === NodeKind::IfColonDelimitedBodyElseClause
                ) {
                    $else = [];
                    foreach ($source->getChildren($child) as $eChild) {
                        if ($eChild->kind === NodeKind::Statement) {
                            $else[] = $eChild;
                        }
                    }
                }
            }
        }

        return [
            'condition' => $condition,
            'then' => $then,
            'elseifs' => $elseifs,
            'else' => $else,
        ];
    }

    /**
     * @return list<Node>
     */
    private static function extractLoopBodyStatements(SourceFile $source, Node $loopNode): array
    {
        $statements = [];
        foreach ($source->getChildren($loopNode) as $child) {
            if ($child->kind === NodeKind::Statement) {
                $statements[] = $child;
            } elseif (
                $child->kind === NodeKind::ForeachBody
                || $child->kind === NodeKind::ForBody
                || $child->kind === NodeKind::WhileBody
            ) {
                foreach ($source->getChildren($child) as $bodyChild) {
                    if ($bodyChild->kind === NodeKind::Statement) {
                        $statements[] = $bodyChild;
                    } elseif (
                        $bodyChild->kind === NodeKind::ForeachColonDelimitedBody
                        || $bodyChild->kind === NodeKind::ForColonDelimitedBody
                        || $bodyChild->kind === NodeKind::WhileColonDelimitedBody
                    ) {
                        foreach ($source->getChildren($bodyChild) as $colonChild) {
                            if ($colonChild->kind === NodeKind::Statement) {
                                $statements[] = $colonChild;
                            }
                        }
                    }
                }
            }
        }

        return $statements;
    }

    /**
     * @return array{try: ?Node, catches: list<Node>, finally: ?Node}
     */
    private static function extractTryParts(SourceFile $source, Node $tryNode): array
    {
        $tryBlock = null;
        $catches = [];
        $finally = null;
        foreach ($source->getChildren($tryNode) as $child) {
            if ($child->kind === NodeKind::Block && $tryBlock === null) {
                $tryBlock = $child;
            } elseif ($child->kind === NodeKind::TryCatchClause) {
                $catches[] = $child;
            } elseif ($child->kind === NodeKind::TryFinallyClause) {
                $finally = $child;
            }
        }

        return ['try' => $tryBlock, 'catches' => $catches, 'finally' => $finally];
    }

    /**
     * @param list<Node> $statements
     */
    private static function statementsContainOffset(array $statements, int $offset): bool
    {
        foreach ($statements as $statement) {
            if ($statement->span->start <= $offset && $statement->span->end > $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<Node>
     */
    private static function scopeStatements(SourceFile $source, Node $scope): array
    {
        if ($scope->kind === NodeKind::Method) {
            $body = self::firstChildOfKind($source, $scope, NodeKind::MethodBody);
            $block = $body !== null ? self::firstChildOfKind($source, $body, NodeKind::Block) : null;
            return $block !== null ? $source->getChildren($block) : [];
        }

        if ($scope->kind === NodeKind::Function || $scope->kind === NodeKind::Closure) {
            $block = self::firstChildOfKind($source, $scope, NodeKind::Block);
            return $block !== null ? $source->getChildren($block) : [];
        }

        if ($scope->kind === NodeKind::ArrowFunction) {
            return [];
        }

        if ($scope->kind->value === 'Namespace') {
            return self::namespaceStatements($source, $scope);
        }

        return $source->getChildren($scope);
    }

    /**
     * @return list<Node>
     */
    private static function namespaceStatements(SourceFile $source, Node $namespace): array
    {
        $body = self::firstChildOfKind($source, $namespace, NodeKind::NamespaceBody);
        if ($body === null) {
            return [];
        }

        $inner = $source->getChildren($body)[0] ?? null;
        if ($inner === null) {
            return [];
        }

        return $source->getChildren($inner);
    }

    private static function firstChildOfKind(SourceFile $source, Node $node, NodeKind $kind): ?Node
    {
        foreach ($source->getChildren($node) as $child) {
            if ($child->kind === $kind) {
                return $child;
            }
        }

        return null;
    }

    private static function unwrapStatement(SourceFile $source, Node $node): ?Node
    {
        $current = $node;
        while ($current !== null && $current->kind === NodeKind::Statement) {
            $current = $source->getChildren($current)[0] ?? null;
        }

        return $current;
    }

    private static function containsExitOrDie(SourceFile $source, Node $statement): bool
    {
        foreach ($source->getDescendants($statement, NodeKind::ExitConstruct) as $node) {
            if (!self::isInsideNestedScope($source, $statement, $node)) {
                return true;
            }
        }
        foreach ($source->getDescendants($statement, NodeKind::DieConstruct) as $node) {
            if (!self::isInsideNestedScope($source, $statement, $node)) {
                return true;
            }
        }

        return false;
    }

    private static function isInsideNestedScope(SourceFile $source, Node $root, Node $node): bool
    {
        $current = $source->getParent($node);
        while ($current !== null && $current->id !== $root->id) {
            if (self::isScopeBoundary($current->kind)) {
                return true;
            }
            $current = $source->getParent($current);
        }

        return false;
    }

    public static function calledMethodName(SourceFile $source, Node $call): ?string
    {
        $member = $source->getChildren($call)[1] ?? null;
        if ($member === null) {
            return null;
        }

        $selector = $source->getChildren($member)[0] ?? null;
        if ($selector === null || $selector->kind !== NodeKind::LocalIdentifier) {
            return null;
        }

        return $source->getText($selector);
    }

    private static function extractAllowedMethodName(SourceFile $source, Node $call): ?string
    {
        $methodName = self::calledMethodName($source, $call);
        if ($methodName === null || strtolower($methodName) !== 'shouldallowmockingmethod') {
            return null;
        }

        $argList = $source->getChildren($call)[2] ?? null;
        if ($argList === null || $argList->kind !== NodeKind::ArgumentList) {
            return null;
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
                return self::extractLiteralString($source, $value);
            }

            break;
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

    private static function enclosingScope(SourceFile $source, Node $node): Node
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
            || $kind === NodeKind::ArrowFunction
            || $kind === NodeKind::Class_
            || $kind === NodeKind::AnonymousClass;
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
            )
        ) {
            $node = $source->getChildren($node)[0] ?? null;
        }

        return $node;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function varId(array $state, string $varName): string
    {
        return $state['vars'][$varName] ?? ('init:' . $varName);
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function killVar(array &$state, string $varName, int &$nextId): void
    {
        self::assignVarId($state, $varName, self::freshId($nextId));
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function assignVarId(array &$state, string $varName, string $newId): void
    {
        $state['vars'][$varName] = $newId;
        if (!isset($state['refs'][$varName])) {
            return;
        }

        $visited = [$varName => true];
        $queue = array_keys($state['refs'][$varName]);
        while (($member = array_pop($queue)) !== null) {
            if (isset($visited[$member])) {
                continue;
            }
            $visited[$member] = true;
            $state['vars'][$member] = $newId;
            foreach (array_keys($state['refs'][$member] ?? []) as $neighbor) {
                if (!isset($visited[$neighbor])) {
                    $queue[] = $neighbor;
                }
            }
        }
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function linkRef(array &$state, string $a, string $b): void
    {
        if ($a === $b) {
            return;
        }

        $state['refs'][$a][$b] = true;
        $state['refs'][$b][$a] = true;
    }

    /**
     * @param array{vars: array<string, string>, allowed: array<string, array<string, true>>, refs: array<string, array<string, true>>, terminated: bool} $state
     */
    private static function detachRef(array &$state, string $varName): void
    {
        if (!isset($state['refs'][$varName])) {
            return;
        }

        foreach (array_keys($state['refs'][$varName]) as $peer) {
            unset($state['refs'][$peer][$varName]);
            if ($state['refs'][$peer] === []) {
                unset($state['refs'][$peer]);
            }
        }
        unset($state['refs'][$varName]);
    }

    private static function freshId(int &$nextId): string
    {
        return 'def:' . ($nextId++);
    }
}
