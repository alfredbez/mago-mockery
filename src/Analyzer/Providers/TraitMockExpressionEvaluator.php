<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeKind;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use PhpToken;

use function count;
use function ltrim;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;

/**
 * @phpstan-import-type CellValue from TraitMockAliasState
 * @phpstan-type CallArgument array{name: ?string, unpacked: bool, start: int, end: int, bareVar: ?string}
 *
 * @internal
 */
final class TraitMockExpressionEvaluator
{
    public const SAFE_MOCKERY_METHODS = [
        'allows' => true, 'andanyotherargs' => true, 'andanyothers' => true, 'andreturn' => true,
        'andreturnarg' => true, 'andreturnnull' => true, 'andreturnself' => true, 'andreturnusing' => true,
        'andreturnvalues' => true, 'andset' => true, 'andthrow' => true, 'any' => true, 'anyof' => true,
        'asundefined' => true, 'atleast' => true, 'atmost' => true, 'between' => true, 'bydefault' => true, 'close' => true,
        'contains' => true, 'ducktype' => true, 'expects' => true, 'fetchmock' => true,
        'getcontainer' => true, 'getmock' => true, 'globally' => true, 'haskey' => true, 'hasvalue' => true,
        'instancemock' => true, 'makepartial' => true, 'mock' => true, 'mustbe' => true, 'namedmock' => true,
        'never' => true, 'not' => true, 'on' => true, 'once' => true, 'ordered' => true, 'passthru' => true,
        'pattern' => true, 'shouldallowmockingmethod' => true, 'shouldallowmockingprotectedmethods' => true,
        'shoulddefermissing' => true, 'shouldhavereceived' => true, 'shouldignoremissing' => true,
        'shouldnothavereceived' => true, 'shouldnotreceive' => true, 'shouldreceive' => true,
        'spy' => true, 'subset' => true,
        'times' => true, 'twice' => true, 'type' => true, 'with' => true, 'withanyargs' => true,
        'withargs' => true, 'withnoargs' => true, 'zeroormoretimes' => true,
    ];

    private const SAFE_MOCK_OBJECT_METHODS = [
        'allows' => true, 'asundefined' => true, 'bydefault' => true, 'expects' => true, 'makepartial' => true,
        'shouldallowmockingmethod' => true, 'shouldallowmockingprotectedmethods' => true,
        'shoulddefermissing' => true, 'shouldhavereceived' => true, 'shouldignoremissing' => true,
        'shouldnothavereceived' => true, 'shouldnotreceive' => true, 'shouldreceive' => true,
    ];
    private const NON_REENTRANT_MOCKERY_METHODS = [
        'andanyotherargs' => true, 'andanyothers' => true, 'any' => true, 'anyof' => true,
        'contains' => true, 'ducktype' => true, 'fetchmock' => true, 'getcontainer' => true,
        'haskey' => true, 'hasvalue' => true, 'instancemock' => true, 'isequal' => true,
        'issame' => true, 'mock' => true, 'mustbe' => true, 'namedmock' => true, 'not' => true,
        'notanyof' => true, 'pattern' => true, 'spy' => true, 'subset' => true, 'type' => true,
    ];
    private const NON_REENTRANT_MOCK_OBJECT_METHODS = [
        'allows' => true, 'asundefined' => true, 'bydefault' => true, 'expects' => true, 'makepartial' => true,
        'shouldallowmockingmethod' => true, 'shouldallowmockingprotectedmethods' => true,
        'shoulddefermissing' => true, 'shouldhavereceived' => true, 'shouldignoremissing' => true,
        'shouldnotreceive' => true, 'shouldreceive' => true,
    ];
    private const EXPECTATION_FACTORY_METHODS = [
        'allows' => true, 'expects' => true, 'shouldnotreceive' => true, 'shouldreceive' => true,
    ];
    private const NON_REENTRANT_EXPECTATION_METHODS = [
        'andreturn' => true, 'andreturnarg' => true, 'andreturnnull' => true, 'andreturns' => true,
        'andreturnself' => true, 'andset' => true, 'atleast' => true, 'atmost' => true, 'between' => true,
        'bydefault' => true, 'getmock' => true, 'globally' => true, 'mock' => true, 'never' => true,
        'once' => true, 'ordered' => true, 'passthru' => true, 'set' => true, 'shouldnotreceive' => true,
        'shouldreceive' => true, 'times' => true, 'twice' => true, 'with' => true, 'withanyargs' => true,
        'withnoargs' => true, 'zeroormoretimes' => true,
    ];
    private const FACTORY_METHODS = ['mock' => true, 'spy' => true, 'namedmock' => true, 'instancemock' => true];
    private const HELPER_FUNCTIONS = ['mock' => true, 'spy' => true, 'namedmock' => true];
    private const FLUENT_PRESERVING_METHODS = [
        'asundefined' => true, 'bydefault' => true, 'makepartial' => true, 'shouldallowmockingmethod' => true,
        'shouldallowmockingprotectedmethods' => true, 'shoulddefermissing' => true, 'shouldignoremissing' => true,
    ];
    private const PREFIX_UNARY_TOKENS = [
        T_PRINT => true, T_INT_CAST => true, T_DOUBLE_CAST => true,
        T_STRING_CAST => true, T_ARRAY_CAST => true, T_OBJECT_CAST => true, T_BOOL_CAST => true, T_UNSET_CAST => true,
    ];
    private const INCLUDE_TOKENS = [
        T_INCLUDE => true, T_INCLUDE_ONCE => true, T_REQUIRE => true, T_REQUIRE_ONCE => true,
    ];

    private bool $reachedTarget = false;

    /**
     * @param Closure(int, int, TraitMockAliasState): bool $walkStatements
     * @param Closure(int, int, TraitMockAliasState): void $resetParameters
     */
    public function __construct(
        private readonly TraitMockTokenScope $syntax,
        private readonly ?Codebase $codebase,
        private readonly ?string $enclosingClass,
        private readonly Closure $walkStatements,
        private readonly Closure $resetParameters,
    ) {}

    public function hasReachedTarget(): bool
    {
        return $this->reachedTarget;
    }

    public function markReachedTarget(): void
    {
        $this->reachedTarget = true;
    }

    public function resetReachedTarget(): void
    {
        $this->reachedTarget = false;
    }

    /** @return CellValue */
    public function evalExpr(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): array
    {
        $tokens = $this->syntax->tokens;
        [$start, $end] = $this->syntax->stripOuterParens($start, $end);
        if ($start >= $end) {
            return TraitMockAliasState::unknownValue();
        }
        if ($stopAtTarget && $tokens[$start]->pos >= $this->syntax->targetPos) {
            $this->reachedTarget = true;

            return TraitMockAliasState::unknownValue();
        }

        $tok = $tokens[$start];
        if ($end - $start === 1) {
            if ($tok->id === T_VARIABLE) {
                return $state->valueOf($tok->text);
            }
            if (
                ($tok->id === T_STRING && strtolower($tok->text) === 'null')
                || ($tok->id === T_NAME_FULLY_QUALIFIED && strtolower($tok->text) === '\\null')
            ) {
                return TraitMockAliasState::nullValue();
            }

            return TraitMockAliasState::unknownValue();
        }

        foreach ([T_LOGICAL_OR, T_LOGICAL_XOR, T_LOGICAL_AND] as $lowOp) {
            $opIdx = $this->syntax->findTopLevelId($start, $end, [$lowOp]);
            if ($opIdx !== null) {
                if ($lowOp === T_LOGICAL_XOR) {
                    $this->evalExpr($start, $opIdx, $state, $stopAtTarget);
                    if (!$this->reachedTarget) {
                        $this->evalExpr($opIdx + 1, $end, $state, $stopAtTarget);
                    }

                    return TraitMockAliasState::unknownValue();
                }

                return $this->evalShortCircuit($start, $opIdx, $end, $state, $stopAtTarget, false);
            }
        }

        $assignIdx = $this->syntax->findTopLevelAssignment($start, $end);
        if ($assignIdx !== null && $this->syntax->isAssignableLhs($start, $assignIdx)) {
            return $this->evalAssignment($start, $assignIdx, $end, $state, $stopAtTarget);
        }

        $ternary = $this->syntax->findTopLevelTernary($start, $end);
        if ($ternary !== null) {
            return $this->evalTernary($start, $ternary[0], $ternary[1], $end, $state, $stopAtTarget);
        }

        foreach ([T_COALESCE, T_BOOLEAN_OR, T_BOOLEAN_AND] as $scOp) {
            $opIdx = $this->syntax->findTopLevelId($start, $end, [$scOp]);
            if ($opIdx !== null) {
                return $this->evalShortCircuit($start, $opIdx, $end, $state, $stopAtTarget, $scOp === T_COALESCE);
            }
        }

        if ($tok->id === T_THROW) {
            $this->evalExpr($start + 1, $end, $state, $stopAtTarget);
            if (!$this->reachedTarget) {
                $state->terminated = true;
            }

            return TraitMockAliasState::unknownValue();
        }
        if ($tok->id === T_YIELD || $tok->id === T_YIELD_FROM) {
            $rest = $start + 1;
            if ($rest + 1 < $end && $tokens[$rest]->text === '&' && $tokens[$rest + 1]->id === T_VARIABLE) {
                $state->markEscaped($tokens[$rest + 1]->text);
                ++$rest;
            }
            $this->evalExpr($rest, $end, $state, $stopAtTarget);

            return TraitMockAliasState::unknownValue();
        }
        if (isset(self::INCLUDE_TOKENS[$tok->id]) || ($tok->id === T_EVAL && $start + 1 < $end && $tokens[$start + 1]->text === '(')) {
            $subStart = $tok->id === T_EVAL ? $start + 2 : $start + 1;
            $subEnd = $tok->id === T_EVAL ? $this->syntax->closeOf($start + 1) : $end;
            $this->evalExpr($subStart, $subEnd, $state, $stopAtTarget);
            if (!$this->reachedTarget) {
                $state->killAll();
            }

            return TraitMockAliasState::unknownValue();
        }
        if (($tok->id === T_ISSET || $tok->id === T_EMPTY) && $start + 1 < $end && $tokens[$start + 1]->text === '(' && $this->syntax->closeOf($start + 1) === $end - 1) {
            $items = $this->syntax->splitTopLevel($start + 2, $end - 1, ',');
            foreach ($items as $idx => [$eStart, $eEnd]) {
                if ($idx === 0 || ($stopAtTarget && $this->syntax->rangeContainsTarget($eStart, $eEnd))) {
                    $this->evalExpr($eStart, $eEnd, $state, $stopAtTarget);
                    if ($this->reachedTarget) {
                        return TraitMockAliasState::unknownValue();
                    }
                    continue;
                }
                $branch = clone $state;
                $this->evalExpr($eStart, $eEnd, $branch, false);
                $state->replaceWith(TraitMockAliasState::join([$state, $branch]));
            }

            return TraitMockAliasState::unknownValue();
        }
        if ($tok->id === T_CLONE) {
            return $this->evalClone($start, $end, $state, $stopAtTarget);
        }
        if (isset(self::PREFIX_UNARY_TOKENS[$tok->id]) || $tok->text === '!' || $tok->text === '~' || $tok->text === '@' || $tok->text === '+' || $tok->text === '-') {
            $this->evalExpr($start + 1, $end, $state, $stopAtTarget);
            if ($tok->id === T_STRING_CAST && !$this->reachedTarget && !$this->isPureLiteralExpr($start + 1, $end)) {
                $state->killEscapedCells();
                $state->killStaticLocalCells();
            }

            return TraitMockAliasState::unknownValue();
        }
        if ($tok->id === T_INC || $tok->id === T_DEC) {
            if ($end - $start === 2 && $tokens[$start + 1]->id === T_VARIABLE) {
                $state->killVar($tokens[$start + 1]->text);
            } else {
                $this->evalExpr($start + 1, $end, $state, $stopAtTarget);
                if (!$this->reachedTarget) {
                    $state->killEscapedCells();
                }
            }

            return TraitMockAliasState::unknownValue();
        }
        if ($tok->id === T_MATCH && $start + 1 < $end && $tokens[$start + 1]->text === '(') {
            return $this->evalMatch($start, $end, $state, $stopAtTarget);
        }
        if ($this->syntax->isClosureStart($start, $end)) {
            $this->evalClosure($start, $end, $state, $stopAtTarget);

            return TraitMockAliasState::unknownValue();
        }
        if ($this->syntax->isArrowFunctionStart($start, $end)) {
            $this->evalArrowFunction($start, $end, $state, $stopAtTarget);

            return TraitMockAliasState::unknownValue();
        }

        $chainValue = $this->tryEvalPostfixChain($start, $end, $state, $stopAtTarget);
        if ($chainValue !== null) {
            return $chainValue;
        }

        if ($tok->text === '[' && $this->syntax->closeOf($start) === $end - 1) {
            $this->evalArrayElements($start + 1, $end - 1, $state, $stopAtTarget);

            return TraitMockAliasState::unknownValue();
        }
        if ($tok->id === T_ARRAY && $start + 1 < $end && $tokens[$start + 1]->text === '(' && $this->syntax->closeOf($start + 1) === $end - 1) {
            $this->evalArrayElements($start + 2, $end - 1, $state, $stopAtTarget);

            return TraitMockAliasState::unknownValue();
        }
        if ($tokens[$end - 1]->id === T_INC || $tokens[$end - 1]->id === T_DEC) {
            if ($end - $start === 2 && $tok->id === T_VARIABLE) {
                $state->killVar($tok->text);
            } else {
                $this->evalExpr($start, $end - 1, $state, $stopAtTarget);
                if (!$this->reachedTarget) {
                    $state->killEscapedCells();
                }
            }

            return TraitMockAliasState::unknownValue();
        }

        $binIdx = $this->syntax->findTopLevelBinaryOperator($start, $end);
        if ($binIdx !== null) {
            $this->evalExpr($start, $binIdx, $state, $stopAtTarget);
            if (!$this->reachedTarget) {
                $this->evalExpr($binIdx + 1, $end, $state, $stopAtTarget);
            }
            if (
                !$this->reachedTarget
                && $tokens[$binIdx]->text === '.'
                && (!$this->isPureLiteralExpr($start, $binIdx) || !$this->isPureLiteralExpr($binIdx + 1, $end))
            ) {
                $state->killEscapedCells();
                $state->killStaticLocalCells();
            }

            return TraitMockAliasState::unknownValue();
        }

        for ($i = $start; $i < $end; ++$i) {
            if ($stopAtTarget && $tokens[$i]->pos >= $this->syntax->targetPos) {
                $this->reachedTarget = true;

                return TraitMockAliasState::unknownValue();
            }
            if (isset($this->syntax->close[$i])) {
                $close = $this->syntax->close[$i];
                $this->evalExpr($i + 1, $close, $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return TraitMockAliasState::unknownValue();
                }
                $i = $close;
            }
        }

        return TraitMockAliasState::unknownValue();
    }

    public function evalCommaExpressions(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): bool
    {
        foreach ($this->syntax->splitTopLevel($start, $end, ',') as [$eStart, $eEnd]) {
            $this->evalExpr($eStart, $eEnd, $state, $stopAtTarget);
            if ($this->reachedTarget) {
                return true;
            }
        }

        return false;
    }

    /** @return list<CallArgument> */
    public function parseCallArguments(int $start, int $end): array
    {
        $tokens = $this->syntax->tokens;
        $parsed = [];
        foreach ($this->syntax->splitTopLevel($start, $end, ',') as [$aStart, $aEnd]) {
            if ($aStart >= $aEnd) {
                continue;
            }
            $name = null;
            if (
                $aStart + 1 < $aEnd
                && ($tokens[$aStart]->id === T_STRING || TraitMockTokenScope::isKeywordIdentifier($tokens[$aStart]))
                && $tokens[$aStart + 1]->text === ':'
            ) {
                $name = $tokens[$aStart]->text;
                $aStart += 2;
            }
            $unpacked = $aStart < $aEnd && $tokens[$aStart]->id === T_ELLIPSIS;
            if ($unpacked) {
                ++$aStart;
            }
            if ($aStart >= $aEnd) {
                continue;
            }
            [$innerStart, $innerEnd] = $this->syntax->stripOuterParens($aStart, $aEnd);
            $bareVar = (!$unpacked && $innerEnd - $innerStart === 1 && $tokens[$innerStart]->id === T_VARIABLE) ? $tokens[$innerStart]->text : null;
            $parsed[] = ['name' => $name, 'unpacked' => $unpacked, 'start' => $aStart, 'end' => $aEnd, 'bareVar' => $bareVar];
        }

        return $parsed;
    }

    /** @param list<CallArgument> $args */
    public function invalidateByRefArguments(
        array $args,
        TraitMockAliasState $state,
        ?FunctionLikeMetadata $calleeMeta,
        bool $knownSafeByValue,
    ): void {
        if ($knownSafeByValue) {
            return;
        }
        $seenUnpack = false;
        foreach ($args as $posIdx => $arg) {
            if ($arg['unpacked']) {
                $seenUnpack = true;
                continue;
            }
            if ($arg['bareVar'] !== null && ($calleeMeta === null || $this->parameterMayBeByRef($calleeMeta, $posIdx, $arg['name'], $seenUnpack))) {
                $state->killVar($arg['bareVar']);
                $state->markEscaped($arg['bareVar']);
            }
        }
    }

    /** @return CellValue */
    private function evalClone(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): array
    {
        $tokens = $this->syntax->tokens;
        $argStart = $start + 1;
        if (
            $argStart < $end
            && $tokens[$argStart]->text === '('
            && $this->syntax->closeOf($argStart) === $end - 1
        ) {
            $args = $this->parseCallArguments($argStart + 1, $end - 1);
            if ($args === []) {
                return TraitMockAliasState::unknownValue();
            }
            $inner = TraitMockAliasState::unknownValue();
            $sawObject = false;
            foreach ($args as $idx => $arg) {
                if ($stopAtTarget && $tokens[$arg['start']]->pos >= $this->syntax->targetPos) {
                    $this->reachedTarget = true;

                    return TraitMockAliasState::unknownValue();
                }
                $argVal = $this->evalExpr($arg['start'], $arg['end'], $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return TraitMockAliasState::unknownValue();
                }
                if (!$arg['unpacked'] && ($arg['name'] === 'object' || ($arg['name'] === null && $idx === 0 && !$sawObject))) {
                    $inner = $argVal;
                    $sawObject = true;
                }
            }
        } else {
            $inner = $this->evalExpr($argStart, $end, $state, $stopAtTarget);
            if ($this->reachedTarget) {
                return TraitMockAliasState::unknownValue();
            }
        }

        $state->killEscapedCells();
        $state->killStaticLocalCells();

        if ($inner['mock']) {
            return TraitMockAliasState::mockValue();
        }
        if ($inner['container']) {
            return TraitMockAliasState::containerValue();
        }

        return TraitMockAliasState::unknownValue();
    }

    /** @return CellValue */
    private function evalShortCircuit(
        int $start,
        int $opIdx,
        int $end,
        TraitMockAliasState $state,
        bool $stopAtTarget,
        bool $preserveValue,
    ): array {
        $lhsVal = $this->evalExpr($start, $opIdx, $state, $stopAtTarget);
        if ($this->reachedTarget) {
            return TraitMockAliasState::unknownValue();
        }

        if ($preserveValue) {
            if ($lhsVal['null']) {
                return $this->evalExpr($opIdx + 1, $end, $state, $stopAtTarget);
            }
            if ($lhsVal['mock'] || $lhsVal['container']) {
                if ($stopAtTarget && $this->syntax->rangeContainsTarget($opIdx + 1, $end)) {
                    $this->evalExpr($opIdx + 1, $end, $state, true);

                    return TraitMockAliasState::unknownValue();
                }

                return $lhsVal;
            }
        }

        if ($stopAtTarget && $this->syntax->rangeContainsTarget($opIdx + 1, $end)) {
            $this->evalExpr($opIdx + 1, $end, $state, true);

            return TraitMockAliasState::unknownValue();
        }
        $rhsState = clone $state;
        $rhsVal = $this->evalExpr($opIdx + 1, $end, $rhsState, false);
        $state->replaceWith(TraitMockAliasState::join([$state, $rhsState]));

        if (!$preserveValue) {
            return TraitMockAliasState::unknownValue();
        }

        $nonNullVal = $lhsVal['mockOrNull'] ? TraitMockAliasState::mockValue() : $lhsVal;

        return $rhsState->terminated ? $nonNullVal : TraitMockAliasState::meetValue($nonNullVal, $rhsVal);
    }

    /** @return CellValue */
    private function evalTernary(int $start, int $qIdx, int $colonIdx, int $end, TraitMockAliasState $state, bool $stopAtTarget): array
    {
        $condVal = $this->evalExpr($start, $qIdx, $state, $stopAtTarget);
        if ($this->reachedTarget) {
            return TraitMockAliasState::unknownValue();
        }
        if ($stopAtTarget && $this->syntax->rangeContainsTarget($qIdx + 1, $colonIdx)) {
            $this->evalExpr($qIdx + 1, $colonIdx, $state, true);

            return TraitMockAliasState::unknownValue();
        }
        if ($stopAtTarget && $this->syntax->rangeContainsTarget($colonIdx + 1, $end)) {
            $this->evalExpr($colonIdx + 1, $end, $state, true);

            return TraitMockAliasState::unknownValue();
        }

        $thenState = clone $state;
        $elseState = clone $state;
        $thenVal = $qIdx + 1 < $colonIdx ? $this->evalExpr($qIdx + 1, $colonIdx, $thenState, false) : $condVal;
        $elseVal = $this->evalExpr($colonIdx + 1, $end, $elseState, false);
        $state->replaceWith(TraitMockAliasState::join([$thenState, $elseState]));

        if ($thenState->terminated && !$elseState->terminated) {
            return $elseVal;
        }
        if (!$thenState->terminated && $elseState->terminated) {
            return $thenVal;
        }

        return TraitMockAliasState::meetValue($thenVal, $elseVal);
    }

    /** @return CellValue */
    private function evalAssignment(int $start, int $assignIdx, int $end, TraitMockAliasState $state, bool $stopAtTarget): array
    {
        $tokens = $this->syntax->tokens;
        $op = $tokens[$assignIdx];
        $rhsStart = $assignIdx + 1;
        [$lhsStart, $lhsEnd] = $this->syntax->stripOuterParens($start, $assignIdx);
        $lhsIsVar = $lhsEnd - $lhsStart === 1 && $tokens[$lhsStart]->id === T_VARIABLE;
        $lhsIsDestruct = $lhsStart < $lhsEnd && ($tokens[$lhsStart]->text === '[' || $tokens[$lhsStart]->id === T_LIST);

        if ($op->text === '=' && $rhsStart < $end && $tokens[$rhsStart]->text === '&') {
            [$rhsCleanStart, $rhsCleanEnd] = $this->syntax->stripOuterParens($rhsStart + 1, $end);
            $rhsIsVar = $rhsCleanEnd - $rhsCleanStart === 1 && $tokens[$rhsCleanStart]->id === T_VARIABLE;
            if (!$lhsIsVar) {
                $this->evalExpr($lhsStart, $lhsEnd, $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return TraitMockAliasState::unknownValue();
                }
            }
            if (!$rhsIsVar) {
                $this->evalExpr($rhsStart + 1, $end, $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return TraitMockAliasState::unknownValue();
                }
            }
            if ($lhsIsVar) {
                $lhsVar = $tokens[$lhsStart]->text;
                $state->assignByRef($lhsVar, $rhsIsVar ? $tokens[$rhsCleanStart]->text : null);

                return $state->valueOf($lhsVar);
            }
            if ($rhsIsVar) {
                $state->markEscaped($tokens[$rhsCleanStart]->text);
            }
            ($lhsStart < $lhsEnd && $tokens[$lhsStart]->text === '$') ? $state->killAll() : $state->killEscapedCells();

            return TraitMockAliasState::unknownValue();
        }

        if (!$lhsIsVar && !$lhsIsDestruct) {
            $this->evalExpr($lhsStart, $lhsEnd, $state, $stopAtTarget);
            if ($this->reachedTarget) {
                return TraitMockAliasState::unknownValue();
            }
        }

        if ($op->id === T_COALESCE_EQUAL) {
            if ($lhsIsVar) {
                $lhsVar = $tokens[$lhsStart]->text;
                $lhsVal = $state->valueOf($lhsVar);

                if ($lhsVal['null']) {
                    $rhsVal = $this->evalExpr($rhsStart, $end, $state, $stopAtTarget);
                    if ($this->reachedTarget) {
                        return TraitMockAliasState::unknownValue();
                    }
                    $state->assignValue($lhsVar, $rhsVal);

                    return $rhsVal;
                }

                if ($lhsVal['mock'] || $lhsVal['container']) {
                    if ($stopAtTarget && $this->syntax->rangeContainsTarget($rhsStart, $end)) {
                        $this->evalExpr($rhsStart, $end, $state, true);

                        return TraitMockAliasState::unknownValue();
                    }

                    return $lhsVal;
                }

                if ($stopAtTarget && $this->syntax->rangeContainsTarget($rhsStart, $end)) {
                    if ($lhsVal['mockOrNull']) {
                        $state->refineCellValue($lhsVar, TraitMockAliasState::nullValue());
                    }
                    $this->evalExpr($rhsStart, $end, $state, true);

                    return TraitMockAliasState::unknownValue();
                }

                $nonNullState = clone $state;
                if ($lhsVal['mockOrNull']) {
                    $nonNullState->refineCellValue($lhsVar, TraitMockAliasState::mockValue());
                }

                $nullState = clone $state;
                if ($lhsVal['mockOrNull']) {
                    $nullState->refineCellValue($lhsVar, TraitMockAliasState::nullValue());
                }
                $rhsVal = $this->evalExpr($rhsStart, $end, $nullState, false);
                $nullState->assignValue($lhsVar, $rhsVal);

                $state->replaceWith(TraitMockAliasState::join([$nonNullState, $nullState]));

                return $state->valueOf($lhsVar);
            }

            if ($stopAtTarget && $this->syntax->rangeContainsTarget($rhsStart, $end)) {
                $this->evalExpr($rhsStart, $end, $state, true);

                return TraitMockAliasState::unknownValue();
            }
            $afterRhs = clone $state;
            $this->evalExpr($rhsStart, $end, $afterRhs, false);
            ($lhsStart < $lhsEnd && ($tokens[$lhsStart]->text === '$' || $tokens[$lhsStart]->text === '$GLOBALS'))
                ? $afterRhs->killAll()
                : $afterRhs->killEscapedCells();
            $state->replaceWith(TraitMockAliasState::join([$state, $afterRhs]));

            return TraitMockAliasState::unknownValue();
        }

        $rhsVal = $this->evalExpr($rhsStart, $end, $state, $stopAtTarget);
        if ($this->reachedTarget) {
            return TraitMockAliasState::unknownValue();
        }
        if ($lhsIsVar) {
            if ($op->text === '=') {
                $state->assignValue($tokens[$lhsStart]->text, $rhsVal);

                return $rhsVal;
            }
            $state->killVar($tokens[$lhsStart]->text);

            return TraitMockAliasState::unknownValue();
        }
        if ($lhsIsDestruct) {
            $this->applyDestructuringTargets($lhsStart, $lhsEnd, $state, $stopAtTarget);
        } else {
            ($lhsStart < $lhsEnd && ($tokens[$lhsStart]->text === '$' || $tokens[$lhsStart]->text === '$GLOBALS'))
                ? $state->killAll()
                : $state->killEscapedCells();
        }

        return TraitMockAliasState::unknownValue();
    }

    /** @return CellValue */
    private function evalMatch(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): array
    {
        $tokens = $this->syntax->tokens;
        $condClose = $this->syntax->closeOf($start + 1);
        $this->evalExpr($start + 2, $condClose, $state, $stopAtTarget);
        if ($this->reachedTarget) {
            return TraitMockAliasState::unknownValue();
        }
        $braceOpen = $condClose + 1;
        if ($braceOpen >= $end || $tokens[$braceOpen]->text !== '{') {
            return TraitMockAliasState::unknownValue();
        }
        $braceClose = $this->syntax->closeOf($braceOpen);

        /** @var list<array{conds: list<array{0: int, 1: int}>, resStart: int, resEnd: int}> $nonDefaultArms */
        $nonDefaultArms = [];
        /** @var ?array{0: int, 1: int} $defaultArm */
        $defaultArm = null;
        $cursor = $braceOpen + 1;
        while ($cursor < $braceClose) {
            $arrowIdx = $this->syntax->findTopLevelId($cursor, $braceClose, [T_DOUBLE_ARROW]);
            if ($arrowIdx === null) {
                break;
            }
            $resEnd = $this->syntax->findTopLevel($arrowIdx + 1, $braceClose, static fn (PhpToken $t): bool => $t->text === ',') ?? $braceClose;
            $conds = $this->syntax->splitTopLevel($cursor, $arrowIdx, ',');
            if (count($conds) === 1 && $conds[0][1] - $conds[0][0] === 1 && $tokens[$conds[0][0]]->id === T_DEFAULT) {
                $defaultArm = [$arrowIdx + 1, $resEnd];
            } else {
                $nonDefaultArms[] = ['conds' => $conds, 'resStart' => $arrowIdx + 1, 'resEnd' => $resEnd];
            }
            $cursor = $resEnd + 1;
        }

        $condState = clone $state;
        $exitStates = [];
        $exitValues = [];
        foreach ($nonDefaultArms as $arm) {
            $matched = [];
            foreach ($arm['conds'] as [$cStart, $cEnd]) {
                if ($stopAtTarget && $this->syntax->rangeContainsTarget($cStart, $cEnd)) {
                    $this->evalExpr($cStart, $cEnd, $condState, true);
                    $state->replaceWith($condState);

                    return TraitMockAliasState::unknownValue();
                }
                $this->evalExpr($cStart, $cEnd, $condState, false);
                $matched[] = clone $condState;
            }
            $armState = TraitMockAliasState::join($matched);
            if ($stopAtTarget && $this->syntax->rangeContainsTarget($arm['resStart'], $arm['resEnd'])) {
                $this->evalExpr($arm['resStart'], $arm['resEnd'], $armState, true);
                $state->replaceWith($armState);

                return TraitMockAliasState::unknownValue();
            }
            $exitValues[] = $this->evalExpr($arm['resStart'], $arm['resEnd'], $armState, false);
            $exitStates[] = $armState;
        }

        if ($defaultArm !== null) {
            $defState = clone $condState;
            if ($stopAtTarget && $this->syntax->rangeContainsTarget($defaultArm[0], $defaultArm[1])) {
                $this->evalExpr($defaultArm[0], $defaultArm[1], $defState, true);
                $state->replaceWith($defState);

                return TraitMockAliasState::unknownValue();
            }
            $exitValues[] = $this->evalExpr($defaultArm[0], $defaultArm[1], $defState, false);
            $exitStates[] = $defState;
        }

        if ($exitStates === []) {
            $state->terminated = true;

            return TraitMockAliasState::unknownValue();
        }
        $result = null;
        foreach ($exitStates as $idx => $exitState) {
            if (!$exitState->terminated) {
                $result = $result === null ? $exitValues[$idx] : TraitMockAliasState::meetValue($result, $exitValues[$idx]);
            }
        }
        $state->replaceWith(TraitMockAliasState::join($exitStates));

        return $result ?? TraitMockAliasState::unknownValue();
    }

    private function evalClosure(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): void
    {
        $tokens = $this->syntax->tokens;
        $i = $start + ($tokens[$start]->id === T_STATIC ? 1 : 0) + 1;
        $i += ($i < $end && $tokens[$i]->text === '&') ? 1 : 0;
        if ($i >= $end || $tokens[$i]->text !== '(') {
            return;
        }
        $paramsStart = $i + 1;
        $paramsEnd = $this->syntax->closeOf($i);
        $i = $paramsEnd + 1;

        /** @var list<array{var: string, byRef: bool}> $captures */
        $captures = [];
        if ($i < $end && $tokens[$i]->id === T_USE && $i + 1 < $end && $tokens[$i + 1]->text === '(') {
            $useClose = $this->syntax->closeOf($i + 1);
            foreach ($this->syntax->splitTopLevel($i + 2, $useClose, ',') as [$uStart, $uEnd]) {
                if ($uStart >= $uEnd) {
                    continue;
                }
                $byRef = $tokens[$uStart]->text === '&';
                $varIdx = $byRef ? $uStart + 1 : $uStart;
                if ($varIdx < $uEnd && $tokens[$varIdx]->id === T_VARIABLE) {
                    $captures[] = ['var' => $tokens[$varIdx]->text, 'byRef' => $byRef];
                }
            }
            $i = $useClose + 1;
        }
        while ($i < $end && $tokens[$i]->text !== '{') {
            ++$i;
        }
        if ($i >= $end) {
            return;
        }
        $bodyClose = $this->syntax->closeOf($i);
        if ($stopAtTarget && $this->syntax->rangeContainsTarget($i + 1, $bodyClose)) {
            $inner = new TraitMockAliasState();
            foreach ($captures as $cap) {
                $cap['byRef'] ? $inner->markEscaped($cap['var']) : $inner->assignValue($cap['var'], $state->valueOf($cap['var']));
            }
            ($this->resetParameters)($paramsStart, $paramsEnd, $inner);
            ($this->walkStatements)($i + 1, $bodyClose, $inner);
            $state->replaceWith($inner);
            $this->reachedTarget = true;

            return;
        }
        foreach ($captures as $cap) {
            if ($cap['byRef']) {
                $state->markEscaped($cap['var']);
            }
        }
    }

    private function evalArrowFunction(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): void
    {
        $tokens = $this->syntax->tokens;
        $i = $start + ($tokens[$start]->id === T_STATIC ? 1 : 0) + 1;
        $i += ($i < $end && $tokens[$i]->text === '&') ? 1 : 0;
        if ($i >= $end || $tokens[$i]->text !== '(') {
            return;
        }
        $paramsStart = $i + 1;
        $paramsEnd = $this->syntax->closeOf($i);
        $arrowIdx = $this->syntax->findTopLevelId($paramsEnd + 1, $end, [T_DOUBLE_ARROW]);
        if ($arrowIdx !== null && $stopAtTarget && $this->syntax->rangeContainsTarget($arrowIdx + 1, $end)) {
            $inner = $state->snapshotValuesOnly();
            ($this->resetParameters)($paramsStart, $paramsEnd, $inner);
            $this->evalExpr($arrowIdx + 1, $end, $inner, true);
            $state->replaceWith($inner);
            $this->reachedTarget = true;
        }
    }

    /** @return ?CellValue */
    private function tryEvalPostfixChain(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): ?array
    {
        $tokens = $this->syntax->tokens;
        $trial = clone $state;
        $i = $start;
        $val = TraitMockAliasState::unknownValue();
        $tok = $tokens[$i];
        $baseIsThis = false;

        if ($tok->text === '(') {
            $close = $this->syntax->closeOf($i);
            if ($close + 1 === $end) {
                return null;
            }
            $val = $this->evalExpr($i + 1, $close, $trial, $stopAtTarget);
            $i = $close + 1;
        } elseif ($tok->id === T_NEW) {
            ++$i;
            while ($i < $end && $tokens[$i]->id === T_ATTRIBUTE) {
                $i = $this->syntax->closeOf($i) + 1;
            }
            if ($i < $end && $tokens[$i]->id === T_CLASS) {
                ++$i;
                if ($i < $end && $tokens[$i]->text === '(') {
                    $i = $this->evalCallAtParen($i, $trial, $stopAtTarget, null, false);
                } else {
                    $trial->killEscapedCells();
                    $trial->killStaticLocalCells();
                }
                while (!$this->reachedTarget && $i < $end && $tokens[$i]->text !== '{') {
                    ++$i;
                }
                if (!$this->reachedTarget && $i < $end) {
                    $i = $this->syntax->closeOf($i) + 1;
                }
            } elseif ($i < $end && TraitMockTokenScope::isNameToken($tokens[$i])) {
                $resolved = TraitMockTokenScope::resolveClassName($tokens[$i], $this->syntax->imports);
                ++$i;
                if ($i < $end && $tokens[$i]->text === '(') {
                    $safe = strtolower($resolved) === 'mockery\\container';
                    $meta = (!$safe && $this->hasBareVariableArgument($i + 1, $this->syntax->closeOf($i)))
                        ? $this->codebase?->getDeclaringMethod($resolved, '__construct')
                        : null;
                    $i = $this->evalCallAtParen($i, $trial, $stopAtTarget, $meta, $safe, $safe);
                } else {
                    $trial->killEscapedCells();
                    $trial->killStaticLocalCells();
                }
                if (strtolower($resolved) === 'mockery\\container') {
                    $val = TraitMockAliasState::containerValue();
                }
            } else {
                return null;
            }
        } elseif ($tok->id === T_VARIABLE) {
            $baseIsThis = $tok->text === '$this';
            $val = $trial->valueOf($tok->text);
            ++$i;
        } elseif (TraitMockTokenScope::isNameToken($tok) && $i + 1 < $end && $tokens[$i + 1]->text === '(') {
            $globalCandidate = strtolower($this->syntax->resolveGlobalFunctionCandidate($tok));
            $safe = isset(self::HELPER_FUNCTIONS[$globalCandidate]);
            $argsClose = $this->syntax->closeOf($i + 1);
            $helperNonReentrant = $safe && $this->isDefinitelyGlobalMockeryHelper($tok, $globalCandidate);
            $meta = (!$safe && ($this->hasBareVariableArgument($i + 2, $argsClose) || ($trial->hasStaticLocalCells() && $this->areArgsPureLiterals($this->parseCallArguments($i + 2, $argsClose)))))
                ? $this->resolveFunctionMetadata($tok)
                : null;
            $i = $this->evalCallAtParen($i + 1, $trial, $stopAtTarget, $meta, $safe, $helperNonReentrant, $helperNonReentrant);
            if (!$this->reachedTarget) {
                if ($globalCandidate === 'extract') {
                    $trial->killAll();
                } elseif ($safe) {
                    $val = TraitMockAliasState::mockValue();
                }
            }
        } elseif (TraitMockTokenScope::isNameToken($tok) && $i + 2 < $end && $tokens[$i + 1]->id === T_DOUBLE_COLON) {
            $className = $this->resolveStaticTargetClass($tok);
            $memberTok = $tokens[$i + 2];
            if ($i + 3 < $end && $tokens[$i + 3]->text === '(' && ($memberTok->id === T_STRING || TraitMockTokenScope::isKeywordIdentifier($memberTok))) {
                $methodLower = strtolower($memberTok->text);
                $isMockery = $className !== null && strtolower($className) === 'mockery';
                $safe = $isMockery && isset(self::SAFE_MOCKERY_METHODS[$methodLower]);
                $nonReentrant = $isMockery && isset(self::NON_REENTRANT_MOCKERY_METHODS[$methodLower]);
                $isFactory = $isMockery && isset(self::FACTORY_METHODS[$methodLower]);
                $meta = (!$safe && $className !== null && $this->hasBareVariableArgument($i + 4, $this->syntax->closeOf($i + 3)))
                    ? $this->codebase?->getDeclaringMethod($className, $memberTok->text)
                    : null;
                $i = $this->evalCallAtParen($i + 3, $trial, $stopAtTarget, $meta, $safe, $nonReentrant, $isFactory);
                if (!$this->reachedTarget && $isMockery) {
                    if (isset(self::FACTORY_METHODS[$methodLower])) {
                        $val = TraitMockAliasState::mockValue();
                    } elseif ($methodLower === 'getcontainer') {
                        $val = TraitMockAliasState::containerValue();
                    }
                }
            } else {
                $i += 3;
            }
        } else {
            return null;
        }

        if ($this->reachedTarget) {
            $state->replaceWith($trial);

            return TraitMockAliasState::unknownValue();
        }

        /** @var list<TraitMockAliasState> $nullsafeSnapshots */
        $nullsafeSnapshots = [];
        $inExpectationChain = false;

        while ($i < $end) {
            $step = $tokens[$i];
            if ($stopAtTarget && $step->pos >= $this->syntax->targetPos) {
                $state->replaceWith($trial);
                $this->reachedTarget = true;

                return TraitMockAliasState::unknownValue();
            }

            if (($step->id === T_OBJECT_OPERATOR || $step->id === T_NULLSAFE_OBJECT_OPERATOR) && $i + 1 < $end) {
                $isNullsafe = $step->id === T_NULLSAFE_OBJECT_OPERATOR;
                if ($isNullsafe) {
                    $nullsafeSnapshots[] = clone $trial;
                }
                $member = $tokens[$i + 1];
                if ($i + 2 < $end && $tokens[$i + 2]->text === '(' && ($member->id === T_STRING || TraitMockTokenScope::isKeywordIdentifier($member))) {
                    $methodLower = strtolower($member->text);
                    $safe = ($val['container'] && isset(self::FACTORY_METHODS[$methodLower]))
                        || ($val['mock'] && isset(self::SAFE_MOCK_OBJECT_METHODS[$methodLower]))
                        || ($inExpectationChain && isset(self::NON_REENTRANT_EXPECTATION_METHODS[$methodLower]));
                    $isContainerFactory = $val['container'] && isset(self::FACTORY_METHODS[$methodLower]);
                    $nonReentrant = $isContainerFactory
                        || ($val['mock'] && isset(self::NON_REENTRANT_MOCK_OBJECT_METHODS[$methodLower]))
                        || ($inExpectationChain && isset(self::NON_REENTRANT_EXPECTATION_METHODS[$methodLower]));
                    $meta = (!$safe && $baseIsThis && $this->enclosingClass !== null && $this->hasBareVariableArgument($i + 3, $this->syntax->closeOf($i + 2)))
                        ? $this->codebase?->getDeclaringMethod($this->enclosingClass, $member->text)
                        : null;
                    $i = $this->evalCallAtParen($i + 2, $trial, $stopAtTarget, $meta, $safe, $nonReentrant, $isContainerFactory);
                    $nextInExpectationChain = ($val['mock'] && isset(self::EXPECTATION_FACTORY_METHODS[$methodLower]))
                        || ($inExpectationChain && isset(self::NON_REENTRANT_EXPECTATION_METHODS[$methodLower]) && $methodLower !== 'getmock' && $methodLower !== 'mock');
                    $val = (!$isNullsafe && (($val['container'] && isset(self::FACTORY_METHODS[$methodLower])) || ($val['mock'] && isset(self::FLUENT_PRESERVING_METHODS[$methodLower]))))
                        ? TraitMockAliasState::mockValue()
                        : TraitMockAliasState::unknownValue();
                    $inExpectationChain = $nextInExpectationChain;
                } elseif ($member->text === '{') {
                    $mClose = $this->syntax->closeOf($i + 1);
                    $this->evalExpr($i + 2, $mClose, $trial, $stopAtTarget);
                    if (!$this->reachedTarget) {
                        $trial->killEscapedCells();
                        $trial->killStaticLocalCells();
                    }
                    $i = $mClose + 1;
                    $val = TraitMockAliasState::unknownValue();
                    $inExpectationChain = false;
                } else {
                    $trial->killEscapedCells();
                    $trial->killStaticLocalCells();
                    $i += 2;
                    $val = TraitMockAliasState::unknownValue();
                    $inExpectationChain = false;
                }
                $baseIsThis = false;
            } elseif ($step->text === '[') {
                $close = $this->syntax->closeOf($i);
                $this->evalExpr($i + 1, $close, $trial, $stopAtTarget);
                if (!$this->reachedTarget) {
                    $trial->killEscapedCells();
                    $trial->killStaticLocalCells();
                }
                $val = TraitMockAliasState::unknownValue();
                $inExpectationChain = false;
                $baseIsThis = false;
                $i = $close + 1;
            } elseif ($step->text === '(') {
                $i = $this->evalCallAtParen($i, $trial, $stopAtTarget, null, false);
                $val = TraitMockAliasState::unknownValue();
                $inExpectationChain = false;
                $baseIsThis = false;
            } elseif ($step->id === T_DOUBLE_COLON && $i + 1 < $end) {
                $i = ($i + 2 < $end && $tokens[$i + 2]->text === '(')
                    ? $this->evalCallAtParen($i + 2, $trial, $stopAtTarget, null, false)
                    : $i + 2;
                $val = TraitMockAliasState::unknownValue();
                $inExpectationChain = false;
                $baseIsThis = false;
            } else {
                return null;
            }

            if ($this->hasReachedTarget()) {
                $state->replaceWith($trial);

                return TraitMockAliasState::unknownValue();
            }
        }

        if ($nullsafeSnapshots !== []) {
            $state->replaceWith(TraitMockAliasState::join([...$nullsafeSnapshots, $trial]));

            return TraitMockAliasState::unknownValue();
        }

        $state->replaceWith($trial);

        return $val;
    }

    private function evalCallAtParen(
        int $openParen,
        TraitMockAliasState $state,
        bool $stopAtTarget,
        ?FunctionLikeMetadata $calleeMeta,
        bool $knownSafeByValue,
        bool $knownSafeNonReentrant = false,
        bool $isMockeryFactory = false,
    ): int {
        $close = $this->syntax->closeOf($openParen);
        $args = $this->parseCallArguments($openParen + 1, $close);
        foreach ($args as $arg) {
            if ($stopAtTarget && $this->syntax->tokens[$arg['start']]->pos >= $this->syntax->targetPos) {
                $this->reachedTarget = true;

                return $close + 1;
            }
            $this->evalExpr($arg['start'], $arg['end'], $state, $stopAtTarget);
            if ($this->reachedTarget) {
                return $close + 1;
            }
        }
        $this->invalidateByRefArguments($args, $state, $calleeMeta, $knownSafeByValue);
        $state->killEscapedCells();
        $isNonReentrant = ($knownSafeNonReentrant && $this->areArgsPureLiterals($args) && (!$isMockeryFactory || $this->hasNoPartialMockSpec($args)))
            || $this->isNonReentrantCallee($calleeMeta, $args);
        if (!$isNonReentrant) {
            $state->killStaticLocalCells();
        }

        return $close + 1;
    }

    /**
     * Partial specs such as `C::class . '[ping]'` trigger `C::__construct()` inside
     * `Mockery\Container::mock()` even without a constructor-arguments array.
     *
     * @param list<CallArgument> $args
     */
    private function hasNoPartialMockSpec(array $args): bool
    {
        $tokens = $this->syntax->tokens;
        foreach ($args as $arg) {
            for ($k = $arg['start']; $k < $arg['end']; ++$k) {
                if ($tokens[$k]->id === T_CONSTANT_ENCAPSED_STRING && str_contains($tokens[$k]->text, '[')) {
                    return false;
                }
            }
        }

        return true;
    }

    private function isDefinitelyGlobalMockeryHelper(PhpToken $tok, string $globalCandidate): bool
    {
        if ($tok->id === T_NAME_FULLY_QUALIFIED) {
            return true;
        }

        $imports = $this->syntax->imports;
        if ($imports['namespace'] === '' || isset($imports['functions'][$globalCandidate])) {
            return true;
        }

        return $this->codebase !== null && $this->codebase->getFunction($imports['namespace'] . '\\' . $tok->text) === null;
    }

    /** @param list<CallArgument> $args */
    public function isPureFunctionCall(PhpToken $tok, array $args): bool
    {
        if (!TraitMockTokenScope::isNameToken($tok) || !$this->areArgsPureLiterals($args)) {
            return false;
        }
        $globalCandidate = strtolower($this->syntax->resolveGlobalFunctionCandidate($tok));
        if (isset(self::HELPER_FUNCTIONS[$globalCandidate])) {
            return false;
        }

        return $this->isNonReentrantCallee($this->resolveFunctionMetadata($tok), $args);
    }

    /** @param list<CallArgument> $args */
    private function isNonReentrantCallee(?FunctionLikeMetadata $meta, array $args): bool
    {
        return $meta !== null
            && $meta->kind === FunctionLikeKind::Function_
            && $meta->flags->contains(MetadataFlags::BUILTIN)
            && $meta->flags->contains(MetadataFlags::PURE)
            && !$meta->flags->contains(MetadataFlags::USER_DEFINED)
            && $this->areArgsPureLiterals($args);
    }

    /** @param list<CallArgument> $args */
    private function areArgsPureLiterals(array $args): bool
    {
        foreach ($args as $arg) {
            if ($arg['unpacked'] || !$this->isPureLiteralExpr($arg['start'], $arg['end'])) {
                return false;
            }
        }

        return true;
    }

    private function isPureLiteralExpr(int $start, int $end): bool
    {
        $tokens = $this->syntax->tokens;
        [$start, $end] = $this->syntax->stripOuterParens($start, $end);
        if ($start >= $end) {
            return false;
        }

        $tok = $tokens[$start];
        if ($end - $start === 1) {
            if ($tok->id === T_LNUMBER || $tok->id === T_DNUMBER || $tok->id === T_CONSTANT_ENCAPSED_STRING) {
                return true;
            }
            $lower = strtolower($tok->text);

            return ($tok->id === T_STRING && ($lower === 'true' || $lower === 'false' || $lower === 'null'))
                || ($tok->id === T_NAME_FULLY_QUALIFIED && ($lower === '\\true' || $lower === '\\false' || $lower === '\\null'));
        }

        if (
            $end - $start === 3
            && (TraitMockTokenScope::isNameToken($tok) || $tok->id === T_STATIC)
            && $tokens[$start + 1]->id === T_DOUBLE_COLON
            && ($tokens[$start + 2]->id === T_CLASS || ($tokens[$start + 2]->id === T_STRING && strtolower($tokens[$start + 2]->text) === 'class'))
        ) {
            return true;
        }

        if ($tok->text === '+' || $tok->text === '-' || $tok->text === '!' || $tok->text === '~') {
            return $this->isPureLiteralExpr($start + 1, $end);
        }

        $dotIdx = $this->syntax->findTopLevel($start, $end, static fn (PhpToken $t): bool => $t->text === '.');
        if ($dotIdx !== null) {
            return $this->isPureLiteralExpr($start, $dotIdx) && $this->isPureLiteralExpr($dotIdx + 1, $end);
        }

        return false;
    }

    private function hasBareVariableArgument(int $start, int $end): bool
    {
        foreach ($this->parseCallArguments($start, $end) as $arg) {
            if ($arg['bareVar'] !== null) {
                return true;
            }
        }

        return false;
    }

    private function parameterMayBeByRef(FunctionLikeMetadata $meta, int $posIdx, ?string $namedArg, bool $afterUnpack): bool
    {
        $params = $meta->parameters;
        $last = $params !== [] ? $params[count($params) - 1] : null;
        if ($namedArg !== null) {
            foreach ($params as $param) {
                if (ltrim($param->name, '$') === $namedArg) {
                    return $param->flags->contains(MetadataFlags::BY_REFERENCE);
                }
            }

            return $last !== null && $last->flags->contains(MetadataFlags::VARIADIC) && $last->flags->contains(MetadataFlags::BY_REFERENCE);
        }
        if ($afterUnpack) {
            for ($i = $posIdx; $i < count($params); ++$i) {
                if ($params[$i]->flags->contains(MetadataFlags::BY_REFERENCE)) {
                    return true;
                }
            }

            return false;
        }
        if (isset($params[$posIdx])) {
            return $params[$posIdx]->flags->contains(MetadataFlags::BY_REFERENCE);
        }

        return $last !== null && $last->flags->contains(MetadataFlags::VARIADIC) && $last->flags->contains(MetadataFlags::BY_REFERENCE);
    }

    private function resolveFunctionMetadata(PhpToken $tok): ?FunctionLikeMetadata
    {
        if ($this->codebase === null) {
            return null;
        }
        $imports = $this->syntax->imports;
        if ($tok->id === T_NAME_FULLY_QUALIFIED) {
            return $this->codebase->getFunction(ltrim($tok->text, '\\'));
        }
        if ($tok->id === T_NAME_RELATIVE) {
            $rest = substr($tok->text, strlen('namespace\\'));

            return $this->codebase->getFunction($imports['namespace'] === '' ? $rest : $imports['namespace'] . '\\' . $rest);
        }
        if ($tok->id === T_NAME_QUALIFIED) {
            return $this->codebase->getFunction(TraitMockTokenScope::resolveClassName($tok, $imports));
        }
        $lower = strtolower($tok->text);
        if (isset($imports['functions'][$lower])) {
            return $this->codebase->getFunction($imports['functions'][$lower]);
        }
        if ($imports['namespace'] !== '') {
            $namespaced = $this->codebase->getFunction($imports['namespace'] . '\\' . $tok->text);
            if ($namespaced !== null) {
                return $namespaced;
            }
        }

        return $this->codebase->getFunction($tok->text);
    }

    private function resolveStaticTargetClass(PhpToken $tok): ?string
    {
        $lower = strtolower($tok->text);
        if ($lower === 'self' || $lower === 'static') {
            return $this->enclosingClass;
        }
        if ($lower === 'parent') {
            return ($this->enclosingClass !== null && $this->codebase !== null)
                ? $this->codebase->getClassLike($this->enclosingClass)?->directParentClass
                : null;
        }

        return TraitMockTokenScope::resolveClassName($tok, $this->syntax->imports);
    }

    private function evalArrayElements(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): void
    {
        $tokens = $this->syntax->tokens;
        foreach ($this->syntax->splitTopLevel($start, $end, ',') as [$eStart, $eEnd]) {
            if ($eStart >= $eEnd) {
                continue;
            }
            $arrowIdx = $this->syntax->findTopLevelId($eStart, $eEnd, [T_DOUBLE_ARROW]);
            $valStart = $eStart;
            if ($arrowIdx !== null) {
                $this->evalExpr($eStart, $arrowIdx, $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return;
                }
                $valStart = $arrowIdx + 1;
            }
            if ($valStart < $eEnd && $tokens[$valStart]->text === '&') {
                if ($valStart + 1 < $eEnd && $tokens[$valStart + 1]->id === T_VARIABLE) {
                    $state->markEscaped($tokens[$valStart + 1]->text);
                }
                ++$valStart;
            } elseif ($valStart < $eEnd && $tokens[$valStart]->id === T_ELLIPSIS) {
                ++$valStart;
            }
            $this->evalExpr($valStart, $eEnd, $state, $stopAtTarget);
            if ($this->reachedTarget) {
                return;
            }
        }
    }

    private function applyDestructuringTargets(int $start, int $end, TraitMockAliasState $state, bool $stopAtTarget): void
    {
        $tokens = $this->syntax->tokens;
        $innerStart = $tokens[$start]->id === T_LIST ? $start + 2 : $start + 1;
        $innerEnd = $tokens[$start]->id === T_LIST ? $this->syntax->closeOf($start + 1) : $end - 1;

        foreach ($this->syntax->splitTopLevel($innerStart, $innerEnd, ',') as [$tStart, $tEnd]) {
            if ($tStart >= $tEnd) {
                continue;
            }
            $arrowIdx = $this->syntax->findTopLevelId($tStart, $tEnd, [T_DOUBLE_ARROW]);
            if ($arrowIdx !== null) {
                $this->evalExpr($tStart, $arrowIdx, $state, $stopAtTarget);
                if ($this->reachedTarget) {
                    return;
                }
                $tStart = $arrowIdx + 1;
            }
            $byRef = $tStart < $tEnd && $tokens[$tStart]->text === '&';
            if ($byRef) {
                ++$tStart;
            }
            if ($tStart >= $tEnd) {
                continue;
            }
            if ($tokens[$tStart]->text === '[' || $tokens[$tStart]->id === T_LIST) {
                $this->applyDestructuringTargets($tStart, $tEnd, $state, $stopAtTarget);
            } elseif ($tEnd - $tStart === 1 && $tokens[$tStart]->id === T_VARIABLE) {
                $byRef ? $state->assignByRef($tokens[$tStart]->text, null) : $state->killVar($tokens[$tStart]->text);
            } else {
                $this->evalExpr($tStart, $tEnd, $state, $stopAtTarget);
                $state->killEscapedCells();
            }
        }
    }
}
