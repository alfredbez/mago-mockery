<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\Codebase;
use PhpToken;

use function bindec;
use function hexdec;
use function max;
use function octdec;
use function preg_match;
use function str_replace;
use function strtolower;
use function substr;

/**
 * @internal
 */
final class TraitMockFlowAnalyzer
{
    private const COMPOUND_ASSIGN_TOKENS = [
        T_PLUS_EQUAL => true, T_MINUS_EQUAL => true, T_MUL_EQUAL => true, T_DIV_EQUAL => true,
        T_MOD_EQUAL => true, T_POW_EQUAL => true, T_CONCAT_EQUAL => true, T_AND_EQUAL => true,
        T_OR_EQUAL => true, T_XOR_EQUAL => true, T_SL_EQUAL => true, T_SR_EQUAL => true, T_COALESCE_EQUAL => true,
    ];
    private const INCLUDE_TOKENS = [
        T_INCLUDE => true, T_INCLUDE_ONCE => true, T_REQUIRE => true, T_REQUIRE_ONCE => true,
    ];
    private const MODIFIER_TOKENS = [
        T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true,
        T_STATIC => true, T_FINAL => true, T_ABSTRACT => true, T_READONLY => true,
    ];
    private const CLASSLIKE_TOKENS = [
        T_CLASS => true, T_INTERFACE => true, T_TRAIT => true, T_ENUM => true,
    ];

    private readonly TraitMockExpressionEvaluator $expr;

    private function __construct(
        private readonly TraitMockTokenScope $syntax,
        ?Codebase $codebase,
        ?string $enclosingClass,
    ) {
        $this->expr = new TraitMockExpressionEvaluator(
            $syntax,
            $codebase,
            $enclosingClass,
            $this->walkStatements(...),
            $this->resetScopeParameters(...),
        );
    }

    /** @param list<PhpToken> $tokens */
    public static function isGuaranteedMockReceiver(
        array $tokens,
        int $receiverStart,
        int $receiverEnd,
        int $targetPos,
        ?Codebase $codebase = null,
    ): bool {
        $imports = TraitMockTokenScope::collectImports($tokens, $receiverStart);
        $syntax = new TraitMockTokenScope($tokens, $targetPos, $imports);
        [$receiverStart, $receiverEnd] = $syntax->stripOuterParens($receiverStart, $receiverEnd);
        if ($receiverStart >= $receiverEnd) {
            return false;
        }
        if ($receiverEnd - $receiverStart === 1 && $tokens[$receiverStart]->id !== T_VARIABLE) {
            return false;
        }

        $scope = TraitMockTokenScope::findEnclosingNamedFunctionOrFileScope($tokens, $receiverStart, $imports);
        $analyzer = new self($syntax, $codebase, $scope['enclosingClass']);
        $state = new TraitMockAliasState();

        if ($scope['paramsStart'] !== null && $scope['paramsEnd'] !== null) {
            $analyzer->resetScopeParameters($scope['paramsStart'], $scope['paramsEnd'], $state);
        }

        $analyzer->walkStatements($scope['bodyStart'], $scope['bodyEnd'], $state);
        if ($state->terminated) {
            return false;
        }

        $analyzer->expr->resetReachedTarget();
        $value = $analyzer->expr->evalExpr($receiverStart, $receiverEnd, $state, false);

        return $value['mock'];
    }

    /** @param list<PhpToken> $tokens */
    public static function findMatchingClose(array $tokens, int $openIdx, string $openChar, string $closeChar): int
    {
        return TraitMockTokenScope::findMatchingClose($tokens, $openIdx, $openChar, $closeChar);
    }

    private function resetScopeParameters(int $start, int $end, TraitMockAliasState $state): void
    {
        $tokens = $this->syntax->tokens;
        foreach ($this->syntax->splitTopLevel($start, $end, ',') as [$pStart, $pEnd]) {
            $i = $pStart;
            while ($i < $pEnd) {
                if ($tokens[$i]->id === T_ATTRIBUTE) {
                    $i = $this->syntax->closeOf($i) + 1;
                } elseif (isset(self::MODIFIER_TOKENS[$tokens[$i]->id])) {
                    ++$i;
                } else {
                    break;
                }
            }
            $varIdx = null;
            for ($j = $i; $j < $pEnd; ++$j) {
                if ($tokens[$j]->id === T_VARIABLE) {
                    $varIdx = $j;
                    break;
                }
                if ($tokens[$j]->text === '=') {
                    break;
                }
            }
            if ($varIdx === null) {
                continue;
            }
            $varName = $tokens[$varIdx]->text;
            $typeEnd = $varIdx;
            $isByRef = false;
            $isVariadic = false;
            while ($typeEnd > $i) {
                $prev = $tokens[$typeEnd - 1];
                if ($prev->text === '&') {
                    $isByRef = true;
                    --$typeEnd;
                } elseif ($prev->id === T_ELLIPSIS) {
                    $isVariadic = true;
                    --$typeEnd;
                } else {
                    break;
                }
            }
            $hasNullDefault = $varIdx + 2 === $pEnd - 1
                && $tokens[$varIdx + 1]->text === '='
                && strtolower($tokens[$varIdx + 2]->text) === 'null';
            $isContainer = !$isVariadic
                && !$hasNullDefault
                && $typeEnd - $i === 1
                && TraitMockTokenScope::isNameToken($tokens[$i])
                && strtolower(TraitMockTokenScope::resolveClassName($tokens[$i], $this->syntax->imports)) === 'mockery\\container';

            $state->resetVar($varName);
            if ($isContainer) {
                $state->assignValue($varName, TraitMockAliasState::containerValue());
            }
            if ($isByRef) {
                $state->markEscaped($varName);
            }
        }
    }

    private function walkStatements(int $start, int $end, TraitMockAliasState $state): bool
    {
        $tokens = $this->syntax->tokens;
        $i = $start;
        while ($i < $end) {
            $tok = $tokens[$i];
            if ($tok->pos >= $this->syntax->targetPos) {
                $this->expr->markReachedTarget();

                return true;
            }
            if ($tok->text === ';' || $tok->id === T_OPEN_TAG || $tok->id === T_CLOSE_TAG || $tok->id === T_INLINE_HTML) {
                ++$i;
                continue;
            }
            if ($this->syntax->isGotoLabel($i, $end)) {
                $state->killAll();
                $i += 2;
                continue;
            }
            $stmtEnd = $this->syntax->findStatementEnd($i, $end);
            $containsTarget = $this->syntax->rangeContainsTarget($i, $stmtEnd);
            if ($this->processStatement($i, $stmtEnd, $state, $containsTarget) || $this->expr->hasReachedTarget()) {
                return true;
            }
            if ($state->terminated) {
                return false;
            }
            $i = max($stmtEnd, $i + 1);
        }

        return false;
    }

    private function processStatement(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $tok = $tokens[$start];

        if ($tok->text === '{') {
            return $this->walkStatements($start + 1, $this->syntax->closeOf($start), $state);
        }
        if ($this->syntax->isSkippedDeclaration($start, $end)) {
            if ($containsTarget) {
                for ($k = $start; $k < $end; ++$k) {
                    if ($tokens[$k]->text === '{') {
                        $close = $this->syntax->closeOf($k);
                        if ($this->syntax->rangeContainsTarget($k + 1, $close)) {
                            return $this->walkStatements($k + 1, $close, $state);
                        }
                    }
                }
            }

            return $containsTarget;
        }

        return match ($tok->id) {
            T_IF => $this->processIf($start, $end, $state, $containsTarget),
            T_TRY => $this->processTry($start, $end, $state, $containsTarget),
            T_FOR, T_WHILE, T_DO, T_FOREACH => $this->processLoop($start, $end, $state, $containsTarget),
            T_SWITCH => $this->processSwitch($start, $end, $state, $containsTarget),
            T_UNSET => $this->processUnset($start, $end, $state, $containsTarget),
            T_GLOBAL => $this->processGlobal($start, $end, $state),
            T_BREAK, T_CONTINUE, T_GOTO => $this->terminate($state),
            T_RETURN, T_THROW => $this->processReturnOrThrow($start, $end, $state, $containsTarget),
            T_EXIT => $this->processExit($start, $end, $state, $containsTarget),
            T_ECHO => $this->expr->evalCommaExpressions($start + 1, $this->syntax->trimTerminator($end), $state, $containsTarget),
            default => $this->processDefaultStatement($start, $end, $state, $containsTarget),
        };
    }

    private function terminate(TraitMockAliasState $state): bool
    {
        $state->terminated = true;

        return false;
    }

    private function processGlobal(int $start, int $end, TraitMockAliasState $state): bool
    {
        foreach ($this->syntax->splitTopLevel($start + 1, $this->syntax->trimTerminator($end), ',') as [$gStart, $gEnd]) {
            if ($gStart < $gEnd && $this->syntax->tokens[$gStart]->id === T_VARIABLE) {
                $state->assignByRef($this->syntax->tokens[$gStart]->text, null);
            }
        }

        return false;
    }

    private function processReturnOrThrow(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $exprEnd = $this->syntax->trimTerminator($end);
        if ($start + 1 < $exprEnd) {
            $this->expr->evalExpr($start + 1, $exprEnd, $state, $containsTarget);
            if ($this->expr->hasReachedTarget()) {
                return true;
            }
        }
        $state->terminated = true;

        return false;
    }

    private function processExit(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        if ($start + 1 < $end && $this->syntax->tokens[$start + 1]->text === '(') {
            $this->expr->evalExpr($start + 2, $this->syntax->closeOf($start + 1), $state, $containsTarget);
            if ($this->expr->hasReachedTarget()) {
                return true;
            }
        }
        $state->terminated = true;

        return false;
    }

    private function processDefaultStatement(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        if ($tokens[$start]->id === T_STATIC && $start + 1 < $end && $tokens[$start + 1]->id === T_VARIABLE) {
            foreach ($this->syntax->splitTopLevel($start + 1, $this->syntax->trimTerminator($end), ',') as [$sStart, $sEnd]) {
                if ($sStart >= $sEnd || $tokens[$sStart]->id !== T_VARIABLE) {
                    continue;
                }
                $varName = $tokens[$sStart]->text;
                if ($sStart + 2 < $sEnd && $tokens[$sStart + 1]->text === '=') {
                    if ($containsTarget && $this->syntax->rangeContainsTarget($sStart + 2, $sEnd)) {
                        $this->expr->evalExpr($sStart + 2, $sEnd, $state, true);

                        return true;
                    }
                    $initState = clone $state;
                    $this->expr->evalExpr($sStart + 2, $sEnd, $initState, false);
                    $state->replaceWith(TraitMockAliasState::join([$state, $initState]));
                }
                $state->bindStaticLocal($varName);
            }

            return false;
        }

        $this->expr->evalExpr($start, $this->syntax->trimTerminator($end), $state, $containsTarget);

        return $this->expr->hasReachedTarget();
    }

    private function processIf(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $condState = clone $state;
        $exitStates = [];
        $hasElse = false;
        $i = $start;

        while ($i < $end) {
            $tok = $tokens[$i];
            $condStart = null;
            $condEnd = null;
            if ($tok->id === T_IF || $tok->id === T_ELSEIF) {
                ++$i;
                if ($i >= $end || $tokens[$i]->text !== '(') {
                    break;
                }
                $condStart = $i + 1;
                $condEnd = $this->syntax->closeOf($i);
                $bodyStart = $condEnd + 1;
                if ($bodyStart < $end && $tokens[$bodyStart]->text === ':') {
                    $bodyEnd = $this->syntax->findColonBlockEnd($bodyStart + 1, $end, [T_ELSEIF, T_ELSE, T_ENDIF]);
                    ++$bodyStart;
                    $nextI = ($bodyEnd < $end && $tokens[$bodyEnd]->id === T_ENDIF) ? $end : $bodyEnd;
                } else {
                    $bodyEnd = $this->syntax->findStatementEnd($bodyStart, $end);
                    $nextI = $bodyEnd;
                }
            } elseif ($tok->id === T_ELSE) {
                if ($i + 1 < $end && $tokens[$i + 1]->id === T_IF) {
                    ++$i;
                    continue;
                }
                $hasElse = true;
                $bodyStart = $i + 1;
                if ($bodyStart < $end && $tokens[$bodyStart]->text === ':') {
                    $bodyEnd = $this->syntax->findColonBlockEnd($bodyStart + 1, $end, [T_ENDIF]);
                    ++$bodyStart;
                } else {
                    $bodyEnd = $this->syntax->findStatementEnd($bodyStart, $end);
                }
                $nextI = $end;
            } else {
                break;
            }

            if ($condStart !== null && $condEnd !== null) {
                if ($containsTarget && $this->syntax->rangeContainsTarget($condStart, $condEnd)) {
                    $this->expr->evalExpr($condStart, $condEnd, $condState, true);
                    $state->replaceWith($condState);

                    return true;
                }
                $this->expr->evalExpr($condStart, $condEnd, $condState, false);
            }

            $branchState = clone $condState;
            if ($containsTarget && $this->syntax->rangeContainsTarget($bodyStart, $bodyEnd)) {
                $this->walkStatements($bodyStart, $bodyEnd, $branchState);
                $state->replaceWith($branchState);

                return true;
            }
            $this->walkStatements($bodyStart, $bodyEnd, $branchState);
            $exitStates[] = $branchState;
            $i = $nextI;
        }

        if (!$hasElse) {
            $exitStates[] = $condState;
        }
        $state->replaceWith(TraitMockAliasState::join($exitStates));

        return false;
    }

    private function processTry(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $i = $start + 1;
        if ($i >= $end || $tokens[$i]->text !== '{') {
            return false;
        }
        $tryClose = $this->syntax->closeOf($i);
        $tryStart = $i + 1;
        $i = $tryClose + 1;

        /** @var list<array{varName: ?string, start: int, end: int}> $catches */
        $catches = [];
        $finallyStart = null;
        $finallyEnd = null;
        while ($i < $end) {
            if ($tokens[$i]->id === T_CATCH) {
                ++$i;
                $varName = null;
                if ($i < $end && $tokens[$i]->text === '(') {
                    $cClose = $this->syntax->closeOf($i);
                    for ($k = $i + 1; $k < $cClose; ++$k) {
                        if ($tokens[$k]->id === T_VARIABLE) {
                            $varName = $tokens[$k]->text;
                        }
                    }
                    $i = $cClose + 1;
                }
                if ($i < $end && $tokens[$i]->text === '{') {
                    $bClose = $this->syntax->closeOf($i);
                    $catches[] = ['varName' => $varName, 'start' => $i + 1, 'end' => $bClose];
                    $i = $bClose + 1;
                    continue;
                }
                break;
            }
            if ($tokens[$i]->id === T_FINALLY && $i + 1 < $end && $tokens[$i + 1]->text === '{') {
                $finallyStart = $i + 2;
                $finallyEnd = $this->syntax->closeOf($i + 1);
                break;
            }
            break;
        }

        $catchEntry = clone $state;
        $this->killWritesInRange($tryStart, $tryClose, $catchEntry);

        $tryState = clone $state;
        if ($containsTarget && $this->syntax->rangeContainsTarget($tryStart, $tryClose)) {
            $this->walkStatements($tryStart, $tryClose, $tryState);
            $state->replaceWith($tryState);

            return true;
        }
        $this->walkStatements($tryStart, $tryClose, $tryState);

        $branches = [$tryState];
        foreach ($catches as $catch) {
            $cState = clone $catchEntry;
            if ($catch['varName'] !== null) {
                $cState->killVar($catch['varName']);
            }
            if ($containsTarget && $this->syntax->rangeContainsTarget($catch['start'], $catch['end'])) {
                $this->walkStatements($catch['start'], $catch['end'], $cState);
                $state->replaceWith($cState);

                return true;
            }
            $this->walkStatements($catch['start'], $catch['end'], $cState);
            $branches[] = $cState;
        }

        if ($finallyStart !== null && $finallyEnd !== null) {
            if ($containsTarget && $this->syntax->rangeContainsTarget($finallyStart, $finallyEnd)) {
                $finEntry = TraitMockAliasState::join([...$branches, $catchEntry], true);
                $this->walkStatements($finallyStart, $finallyEnd, $finEntry);
                $state->replaceWith($finEntry);

                return true;
            }
            $joined = TraitMockAliasState::join($branches);
            if (!$joined->terminated) {
                $this->walkStatements($finallyStart, $finallyEnd, $joined);
            }
            $state->replaceWith($joined);

            return false;
        }

        $state->replaceWith(TraitMockAliasState::join($branches));

        return false;
    }

    private function processLoop(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $kind = $tokens[$start]->id;

        if ($kind === T_DO) {
            $bodyStart = $start + 1;
            $bodyEnd = $this->syntax->findStatementEnd($bodyStart, $end);
            $hasCondition = $bodyEnd + 1 < $end && $tokens[$bodyEnd]->id === T_WHILE && $tokens[$bodyEnd + 1]->text === '(';
            $condStart = $hasCondition ? $bodyEnd + 2 : null;
            $condEnd = $hasCondition ? $this->syntax->closeOf($bodyEnd + 1) : null;

            if ($condStart !== null && $condEnd !== null && $this->isStaticallyFalseCondition($condStart, $condEnd)) {
                if ($containsTarget && $this->syntax->rangeContainsTarget($bodyStart, $bodyEnd)) {
                    return $this->walkStatements($bodyStart, $bodyEnd, $state);
                }
                if (!$containsTarget && !$this->hasLoopControlJump($bodyStart, $bodyEnd)) {
                    $this->walkStatements($bodyStart, $bodyEnd, $state);

                    return false;
                }
                $this->killWritesInRange($bodyStart, $bodyEnd, $state);

                return false;
            }

            $this->killWritesInRange($bodyStart, $end, $state);
            if ($containsTarget && $this->syntax->rangeContainsTarget($bodyStart, $bodyEnd)) {
                return $this->walkStatements($bodyStart, $bodyEnd, $state);
            }
            if ($containsTarget && $condStart !== null && $condEnd !== null) {
                $this->expr->evalExpr($condStart, $condEnd, $state, true);

                return true;
            }

            return false;
        }

        if ($start + 1 >= $end || $tokens[$start + 1]->text !== '(') {
            $this->killWritesInRange($start, $end, $state);

            return $containsTarget;
        }

        $headerClose = $this->syntax->closeOf($start + 1);
        $bodyStart = $headerClose + 1;
        $bodyEnd = $end;
        if ($bodyStart < $end && $tokens[$bodyStart]->text === ':') {
            ++$bodyStart;
            $bodyEnd = max($bodyStart, $end - 2);
        }

        if ($kind === T_FOR) {
            $parts = $this->syntax->splitTopLevel($start + 2, $headerClose, ';');
            if (isset($parts[0]) && $this->expr->evalCommaExpressions($parts[0][0], $parts[0][1], $state, $containsTarget)) {
                return true;
            }
            if (isset($parts[1])) {
                $this->killWritesInRange($parts[1][0], $parts[1][1], $state);
            }
            if (isset($parts[2])) {
                $this->killWritesInRange($parts[2][0], $parts[2][1], $state);
            }
            $this->killWritesInRange($bodyStart, $bodyEnd, $state);
            if (isset($parts[1]) && $this->expr->evalCommaExpressions($parts[1][0], $parts[1][1], $state, $containsTarget)) {
                return true;
            }
            if ($containsTarget && $this->syntax->rangeContainsTarget($bodyStart, $bodyEnd)) {
                return $this->walkStatements($bodyStart, $bodyEnd, $state);
            }
            if ($containsTarget && isset($parts[2]) && $this->syntax->rangeContainsTarget($parts[2][0], $parts[2][1])) {
                $this->expr->evalCommaExpressions($parts[2][0], $parts[2][1], $state, true);

                return true;
            }

            return false;
        }

        if ($kind === T_WHILE) {
            $this->killWritesInRange($start + 2, $headerClose, $state);
            $this->killWritesInRange($bodyStart, $bodyEnd, $state);
            $this->expr->evalExpr($start + 2, $headerClose, $state, $containsTarget);
            if ($this->expr->hasReachedTarget()) {
                return true;
            }
        } else {
            $asIdx = $this->syntax->findTopLevelId($start + 2, $headerClose, [T_AS]);
            if ($asIdx !== null) {
                $this->expr->evalExpr($start + 2, $asIdx, $state, $containsTarget);
                if ($this->expr->hasReachedTarget()) {
                    return true;
                }
                $state->killEscapedCells();
                $state->killStaticLocalCells();
                $this->killForeachTargets($asIdx + 1, $headerClose, $state);
            }
            $this->killWritesInRange($bodyStart, $bodyEnd, $state);
        }

        return $containsTarget && $this->syntax->rangeContainsTarget($bodyStart, $bodyEnd)
            ? $this->walkStatements($bodyStart, $bodyEnd, $state)
            : false;
    }

    private function processSwitch(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $i = $start + 1;
        if ($i < $end && $tokens[$i]->text === '(') {
            $condClose = $this->syntax->closeOf($i);
            $this->expr->evalExpr($i + 1, $condClose, $state, $containsTarget);
            if ($this->expr->hasReachedTarget()) {
                return true;
            }
            $i = $condClose + 1;
        }

        $this->killWritesInRange($i, $end, $state);
        if (!$containsTarget) {
            return false;
        }

        $bodyStart = $i;
        $bodyEnd = $end;
        if ($i < $end && $tokens[$i]->text === '{') {
            $bodyStart = $i + 1;
            $bodyEnd = $this->syntax->closeOf($i);
        } elseif ($i < $end && $tokens[$i]->text === ':') {
            $bodyStart = $i + 1;
            $bodyEnd = max($bodyStart, $end - 2);
        }

        $caseStart = $this->findContainingCaseStart($bodyStart, $bodyEnd);

        return ($caseStart !== null && $caseStart < $bodyEnd)
            ? $this->walkStatements($caseStart, $bodyEnd, $state)
            : true;
    }

    private function findContainingCaseStart(int $bodyStart, int $bodyEnd): ?int
    {
        $tokens = $this->syntax->tokens;
        $caseStart = null;
        for ($j = $bodyStart; $j < $bodyEnd; ++$j) {
            $tok = $tokens[$j];
            if ($tok->pos >= $this->syntax->targetPos) {
                break;
            }
            if (TraitMockTokenScope::isOpenBrace($tok)) {
                $close = $this->syntax->closeOf($j);
                if ($this->syntax->rangeContainsTarget($j, $close + 1)) {
                    break;
                }
                $j = $close;
                continue;
            }
            if (($tok->id === T_CASE || $tok->id === T_DEFAULT) && !TraitMockTokenScope::isMemberAccess($tokens, $j)) {
                $k = $j + 1;
                while ($k < $bodyEnd && $tokens[$k]->text !== ':' && $tokens[$k]->text !== ';') {
                    $k = isset($this->syntax->close[$k]) ? $this->syntax->close[$k] + 1 : $k + 1;
                }
                if ($k < $bodyEnd) {
                    $caseStart = $k + 1;
                    $j = $k;
                }
            }
        }

        return $caseStart;
    }

    private function processUnset(int $start, int $end, TraitMockAliasState $state, bool $containsTarget): bool
    {
        $tokens = $this->syntax->tokens;
        $i = $start + 1;
        if ($i >= $end || $tokens[$i]->text !== '(') {
            return false;
        }
        foreach ($this->syntax->splitTopLevel($i + 1, $this->syntax->closeOf($i), ',') as [$argStart, $argEnd]) {
            [$argStart, $argEnd] = $this->syntax->stripOuterParens($argStart, $argEnd);
            if ($argStart >= $argEnd) {
                continue;
            }
            if ($containsTarget && $this->syntax->rangeContainsTarget($argStart, $argEnd)) {
                $this->expr->evalExpr($argStart, $argEnd, $state, true);

                return true;
            }
            if ($argEnd - $argStart === 1 && $tokens[$argStart]->id === T_VARIABLE) {
                $state->unsetVar($tokens[$argStart]->text);
            } else {
                $this->expr->evalExpr($argStart, $argEnd, $state, false);
            }
            $state->killEscapedCells();
            $state->killStaticLocalCells();
        }

        return false;
    }

    private function killWritesInRange(int $start, int $end, TraitMockAliasState $state): void
    {
        $tokens = $this->syntax->tokens;
        for ($i = $start; $i < $end; ++$i) {
            $tok = $tokens[$i];

            if ($tok->id === T_NEW) {
                $j = $i + 1;
                while ($j < $end && $tokens[$j]->id === T_ATTRIBUTE) {
                    $j = $this->syntax->closeOf($j) + 1;
                }
                if ($j < $end && $tokens[$j]->id === T_CLASS) {
                    ++$j;
                    if ($j < $end && $tokens[$j]->text === '(') {
                        $argsClose = $this->syntax->closeOf($j);
                        $this->killWritesInRange($j + 1, $argsClose, $state);
                        $this->expr->invalidateByRefArguments($this->expr->parseCallArguments($j + 1, $argsClose), $state, null, false);
                        $j = $argsClose + 1;
                    }
                    $state->killEscapedCells();
                    $state->killStaticLocalCells();
                    while ($j < $end && $tokens[$j]->text !== '{') {
                        ++$j;
                    }
                    if ($j < $end) {
                        $i = $this->syntax->closeOf($j);
                    }
                    continue;
                }
                $state->killEscapedCells();
                $state->killStaticLocalCells();
                continue;
            }

            if (isset(self::CLASSLIKE_TOKENS[$tok->id]) && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1;
                while ($j < $end && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $end && $tokens[$j]->text === '{') {
                    $i = $this->syntax->closeOf($j);
                }
                continue;
            }

            if ($tok->id === T_FUNCTION && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1 + ($i + 1 < $end && $tokens[$i + 1]->text === '&' ? 1 : 0);
                $isClosure = $j < $end && $tokens[$j]->text === '(';
                while ($j < $end && $tokens[$j]->text !== '(' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $end && $tokens[$j]->text === '(') {
                    $j = $this->syntax->closeOf($j) + 1;
                }
                if ($isClosure && $j < $end && $tokens[$j]->id === T_USE && $j + 1 < $end && $tokens[$j + 1]->text === '(') {
                    $uClose = $this->syntax->closeOf($j + 1);
                    for ($k = $j + 2; $k < $uClose; ++$k) {
                        if ($tokens[$k]->text === '&' && $k + 1 < $uClose && $tokens[$k + 1]->id === T_VARIABLE) {
                            $state->killVar($tokens[$k + 1]->text);
                            $state->markEscaped($tokens[$k + 1]->text);
                        }
                    }
                    $j = $uClose + 1;
                }
                while ($j < $end && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $end && $tokens[$j]->text === '{') {
                    $i = $this->syntax->closeOf($j);
                }
                continue;
            }

            if ($tok->id === T_FN && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1 + ($i + 1 < $end && $tokens[$i + 1]->text === '&' ? 1 : 0);
                if ($j < $end && $tokens[$j]->text === '(') {
                    $j = $this->syntax->closeOf($j) + 1;
                }
                $arrowIdx = $this->syntax->findTopLevelId($j, $end, [T_DOUBLE_ARROW]);
                if ($arrowIdx !== null) {
                    $i = $this->syntax->findArrowBodyEnd($arrowIdx + 1, $end) - 1;
                }
                continue;
            }

            if ($tok->id === T_FOREACH && $i + 1 < $end && $tokens[$i + 1]->text === '(') {
                $close = $this->syntax->closeOf($i + 1);
                $asIdx = $this->syntax->findTopLevelId($i + 2, $close, [T_AS]);
                if ($asIdx !== null) {
                    $this->killForeachTargets($asIdx + 1, $close, $state);
                }
                continue;
            }

            if ($tok->id === T_UNSET && $i + 1 < $end && $tokens[$i + 1]->text === '(') {
                $close = $this->syntax->closeOf($i + 1);
                foreach ($this->syntax->splitTopLevel($i + 2, $close, ',') as [$aStart, $aEnd]) {
                    if ($aEnd - $aStart === 1 && $tokens[$aStart]->id === T_VARIABLE) {
                        $state->maybeDetachVar($tokens[$aStart]->text);
                    } else {
                        $this->killWritesInRange($aStart, $aEnd, $state);
                    }
                }
                $i = $close;
                continue;
            }

            if ($tok->id === T_STATIC && $i + 1 < $end && $tokens[$i + 1]->id === T_VARIABLE) {
                $sEnd = $this->syntax->scanToStatementTerminator($i + 1, $end);
                foreach ($this->syntax->splitTopLevel($i + 1, $this->syntax->trimTerminator($sEnd), ',') as [$sStart, $sEndPart]) {
                    if ($sStart < $sEndPart && $tokens[$sStart]->id === T_VARIABLE) {
                        if ($sStart + 2 < $sEndPart && $tokens[$sStart + 1]->text === '=') {
                            $this->killWritesInRange($sStart + 2, $sEndPart, $state);
                        }
                        $state->maybeDetachAndKillVar($tokens[$sStart]->text);
                        $state->markStaticLocal($tokens[$sStart]->text);
                    }
                }
                $i = $sEnd - 1;
                continue;
            }

            if ($tok->id === T_CLONE && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $state->killEscapedCells();
                $state->killStaticLocalCells();
                continue;
            }

            if ($tok->id === T_GLOBAL) {
                $gEnd = $this->syntax->scanToStatementTerminator($i + 1, $end);
                for ($k = $i + 1; $k < $gEnd; ++$k) {
                    if ($tokens[$k]->id === T_VARIABLE) {
                        $state->maybeDetachAndKillVar($tokens[$k]->text);
                        $state->markEscaped($tokens[$k]->text);
                    }
                }
                continue;
            }

            if ($tok->id === T_EVAL || isset(self::INCLUDE_TOKENS[$tok->id])) {
                $state->killAll();
                continue;
            }

            if ($tok->id === T_INC || $tok->id === T_DEC) {
                if ($i > $start && $tokens[$i - 1]->id === T_VARIABLE) {
                    $state->maybeDetachAndKillVar($tokens[$i - 1]->text);
                } elseif ($i + 1 < $end && $tokens[$i + 1]->id === T_VARIABLE) {
                    $state->maybeDetachAndKillVar($tokens[$i + 1]->text);
                } else {
                    $state->killEscapedCells();
                }
                continue;
            }

            if ($tok->text === '=' || isset(self::COMPOUND_ASSIGN_TOKENS[$tok->id])) {
                if ($tok->text === '=' && $i + 2 < $end && $tokens[$i + 1]->text === '&' && $tokens[$i + 2]->id === T_VARIABLE) {
                    $state->killVar($tokens[$i + 2]->text);
                    $state->markEscaped($tokens[$i + 2]->text);
                }
                $this->killAssignmentLhsInScan($start, $i, $state);
                continue;
            }

            if ($tok->text === '&' && $i + 1 < $end && $tokens[$i + 1]->id === T_VARIABLE && ($i === $start || !TraitMockTokenScope::canEndExpression($tokens[$i - 1]))) {
                $state->markEscaped($tokens[$i + 1]->text);
                continue;
            }

            if ($tok->text === '(' && $i > $start) {
                $prev = $tokens[$i - 1];
                if (
                    $prev->id !== T_ISSET && $prev->id !== T_EMPTY && $prev->id !== T_ARRAY && $prev->id !== T_LIST && $prev->id !== T_CLONE
                    && (TraitMockTokenScope::isNameToken($prev) || TraitMockTokenScope::isKeywordIdentifier($prev) || $prev->id === T_VARIABLE || $prev->text === ')' || $prev->text === ']')
                ) {
                    $args = $this->expr->parseCallArguments($i + 1, $this->syntax->closeOf($i));
                    $lowerPrev = strtolower($prev->text);
                    if ($lowerPrev === 'extract') {
                        $state->killAll();
                    } else {
                        $this->expr->invalidateByRefArguments($args, $state, null, false);
                    }
                    $state->killEscapedCells();
                    if ($state->hasStaticLocalCells()) {
                        $isPlainFnCall = !TraitMockTokenScope::isMemberAccess($tokens, $i - 1)
                            && ($i - 2 < $start || $tokens[$i - 2]->id !== T_NEW);
                        if (!$isPlainFnCall || !$this->expr->isPureFunctionCall($prev, $args)) {
                            $state->killStaticLocalCells();
                        }
                    }
                }
            }
        }
    }

    private function isStaticallyFalseCondition(int $start, int $end): bool
    {
        $tokens = $this->syntax->tokens;
        [$start, $end] = $this->syntax->stripOuterParens($start, $end);
        if ($start >= $end) {
            return false;
        }

        $len = $end - $start;
        $first = $tokens[$start];
        if ($len === 1) {
            $lower = strtolower($first->text);
            if (
                ($first->id === T_STRING && ($lower === 'false' || $lower === 'null'))
                || ($first->id === T_NAME_FULLY_QUALIFIED && ($lower === '\\false' || $lower === '\\null'))
            ) {
                return true;
            }
            if ($first->id === T_LNUMBER) {
                return preg_match('/^(?:0[xX]0[0_]*|0[bB]0[0_]*|0[oO]0[0_]*|0[0_]*)$/', $first->text) === 1;
            }
            if ($first->id === T_DNUMBER) {
                return (float) str_replace('_', '', $first->text) === 0.0;
            }
            if ($first->id === T_CONSTANT_ENCAPSED_STRING) {
                return $first->text === "''" || $first->text === '""' || $first->text === "'0'" || $first->text === '"0"';
            }

            return false;
        }

        if ($len === 2 && $first->text === '[' && $tokens[$start + 1]->text === ']') {
            return true;
        }
        if ($len === 3 && $first->id === T_ARRAY && $tokens[$start + 1]->text === '(' && $tokens[$start + 2]->text === ')') {
            return true;
        }
        if ($first->text === '!') {
            return $this->isStaticallyTrueCondition($start + 1, $end);
        }

        return false;
    }

    private function isStaticallyTrueCondition(int $start, int $end): bool
    {
        $tokens = $this->syntax->tokens;
        [$start, $end] = $this->syntax->stripOuterParens($start, $end);
        if ($start >= $end) {
            return false;
        }

        $len = $end - $start;
        $first = $tokens[$start];
        if ($len === 1) {
            $lower = strtolower($first->text);
            if (
                ($first->id === T_STRING && $lower === 'true')
                || ($first->id === T_NAME_FULLY_QUALIFIED && $lower === '\\true')
            ) {
                return true;
            }
            if ($first->id === T_LNUMBER) {
                return preg_match('/^(?:0[xX]0[0_]*|0[bB]0[0_]*|0[oO]0[0_]*|0[0_]*)$/', $first->text) !== 1;
            }

            return false;
        }

        if ($first->text === '!') {
            return $this->isStaticallyFalseCondition($start + 1, $end);
        }

        return false;
    }

    private function hasLoopControlJump(int $start, int $end, int $depth = 0): bool
    {
        $tokens = $this->syntax->tokens;
        for ($i = $start; $i < $end; ++$i) {
            $tok = $tokens[$i];
            if (isset(self::CLASSLIKE_TOKENS[$tok->id]) && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1;
                while ($j < $end && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $end && $tokens[$j]->text === '{') {
                    $i = $this->syntax->closeOf($j);
                }
                continue;
            }
            if ($tok->id === T_FUNCTION && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1;
                while ($j < $end && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $end && $tokens[$j]->text === '{') {
                    $i = $this->syntax->closeOf($j);
                }
                continue;
            }
            if ($tok->id === T_FN && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $j = $i + 1 + ($i + 1 < $end && $tokens[$i + 1]->text === '&' ? 1 : 0);
                if ($j < $end && $tokens[$j]->text === '(') {
                    $j = $this->syntax->closeOf($j) + 1;
                }
                $arrowIdx = $this->syntax->findTopLevelId($j, $end, [T_DOUBLE_ARROW]);
                if ($arrowIdx !== null) {
                    $i = $this->syntax->findArrowBodyEnd($arrowIdx + 1, $end) - 1;
                }
                continue;
            }
            if (
                ($tok->id === T_SWITCH || $tok->id === T_FOR || $tok->id === T_FOREACH || $tok->id === T_WHILE)
                && !TraitMockTokenScope::isMemberAccess($tokens, $i)
            ) {
                $bodyStart = ($i + 1 < $end && $tokens[$i + 1]->text === '(')
                    ? $this->syntax->closeOf($i + 1) + 1
                    : $i + 1;
                if ($bodyStart < $end && $tokens[$bodyStart]->text === ':') {
                    $endTok = match ($tok->id) {
                        T_FOR => T_ENDFOR,
                        T_FOREACH => T_ENDFOREACH,
                        T_WHILE => T_ENDWHILE,
                        default => T_ENDSWITCH,
                    };
                    $bodyEnd = $this->syntax->findColonBlockEnd($bodyStart + 1, $end, [$endTok]);
                    if ($this->hasLoopControlJump($bodyStart + 1, $bodyEnd, $depth + 1)) {
                        return true;
                    }
                    $i = max($this->syntax->scanToStatementTerminator($bodyEnd + 1, $end) - 1, $i);
                    continue;
                }
                if ($bodyStart < $end && $tokens[$bodyStart]->text === '{') {
                    $bodyClose = $this->syntax->closeOf($bodyStart);
                    if ($this->hasLoopControlJump($bodyStart + 1, $bodyClose, $depth + 1)) {
                        return true;
                    }
                    $i = $bodyClose;
                    continue;
                }
                if ($bodyStart < $end) {
                    $bodyEnd = $this->syntax->findStatementEnd($bodyStart, $end);
                    if ($this->hasLoopControlJump($bodyStart, $bodyEnd, $depth + 1)) {
                        return true;
                    }
                    $i = max($bodyEnd - 1, $i);
                    continue;
                }
            }
            if ($tok->id === T_DO && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $bodyStart = $i + 1;
                $bodyEnd = $this->syntax->findStatementEnd($bodyStart, $end);
                if ($this->hasLoopControlJump($bodyStart, $bodyEnd, $depth + 1)) {
                    return true;
                }
                $afterDo = ($bodyEnd < $end && $tokens[$bodyEnd]->id === T_WHILE)
                    ? $this->syntax->scanToStatementTerminator($bodyEnd + 1, $end)
                    : $bodyEnd;
                $i = max($afterDo - 1, $i);
                continue;
            }
            if ($tok->id === T_GOTO && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                return true;
            }
            if (($tok->id === T_BREAK || $tok->id === T_CONTINUE) && !TraitMockTokenScope::isMemberAccess($tokens, $i)) {
                $levels = $this->parseBreakContinueLevels($i, $end);
                if ($levels === null || $levels > $depth) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseBreakContinueLevels(int $keywordIndex, int $end): ?int
    {
        $tokens = $this->syntax->tokens;
        $next = $keywordIndex + 1;
        if ($next >= $end || $tokens[$next]->text === ';' || $tokens[$next]->id === T_CLOSE_TAG) {
            return 1;
        }

        if (
            $tokens[$next]->id !== T_LNUMBER
            || ($next + 1 < $end && $tokens[$next + 1]->text !== ';' && $tokens[$next + 1]->id !== T_CLOSE_TAG)
        ) {
            return null;
        }

        $raw = str_replace('_', '', $tokens[$next]->text);
        if (preg_match('/^0[xX][0-9a-fA-F]+$/', $raw) === 1) {
            $levels = (int) hexdec(substr($raw, 2));
        } elseif (preg_match('/^0[bB][01]+$/', $raw) === 1) {
            $levels = (int) bindec(substr($raw, 2));
        } elseif (preg_match('/^0[oO][0-7]+$/', $raw) === 1) {
            $levels = (int) octdec(substr($raw, 2));
        } elseif (preg_match('/^0[0-7]+$/', $raw) === 1) {
            $levels = (int) octdec($raw);
        } elseif (preg_match('/^[0-9]+$/', $raw) === 1) {
            $levels = (int) $raw;
        } else {
            return null;
        }

        return $levels >= 1 ? $levels : null;
    }

    private function killAssignmentLhsInScan(int $floor, int $assignIdx, TraitMockAliasState $state): void
    {
        $tokens = $this->syntax->tokens;
        if ($assignIdx <= $floor) {
            return;
        }
        $prev = $tokens[$assignIdx - 1];
        if ($prev->id === T_VARIABLE) {
            $before = $assignIdx - 2 >= $floor ? $tokens[$assignIdx - 2] : null;
            if ($before !== null && ($before->text === '$' || $before->id === T_DOUBLE_COLON)) {
                $before->text === '$' ? $state->killAll() : $state->killEscapedCells();
            } else {
                $state->maybeDetachAndKillVar($prev->text);
            }

            return;
        }
        if ($prev->text === ']' || $prev->text === ')') {
            $openIdx = $this->syntax->openOf($assignIdx - 1, $floor);
            if ($prev->text === ']' && $openIdx > $floor && $tokens[$openIdx - 1]->text === '$GLOBALS') {
                $state->killAll();

                return;
            }
            if (
                ($prev->text === ']' && ($openIdx === $floor || !TraitMockTokenScope::canEndExpression($tokens[$openIdx - 1])))
                || ($prev->text === ')' && $openIdx > $floor && $tokens[$openIdx - 1]->id === T_LIST)
            ) {
                $this->killDestructVarsInScan($openIdx + 1, $assignIdx - 1, $state);

                return;
            }
        }
        $state->killEscapedCells();
    }

    private function killDestructVarsInScan(int $start, int $end, TraitMockAliasState $state): void
    {
        $tokens = $this->syntax->tokens;
        for ($k = $start; $k < $end; ++$k) {
            if ($tokens[$k]->id === T_VARIABLE) {
                $state->maybeDetachAndKillVar($tokens[$k]->text);
                if ($k > $start && $tokens[$k - 1]->text === '&') {
                    $state->markEscaped($tokens[$k]->text);
                }
            }
        }
    }

    private function killForeachTargets(int $start, int $end, TraitMockAliasState $state): void
    {
        $tokens = $this->syntax->tokens;
        $arrowIdx = $this->syntax->findTopLevelId($start, $end, [T_DOUBLE_ARROW]);
        if ($arrowIdx !== null) {
            if ($start < $arrowIdx && $tokens[$start]->id === T_VARIABLE) {
                $state->maybeDetachAndKillVar($tokens[$start]->text);
            }
            $start = $arrowIdx + 1;
        }
        if ($start >= $end) {
            return;
        }
        if ($tokens[$start]->text === '&' && $start + 1 < $end && $tokens[$start + 1]->id === T_VARIABLE) {
            $state->maybeDetachVar($tokens[$start + 1]->text);
            $state->markEscaped($tokens[$start + 1]->text);
        } elseif ($tokens[$start]->id === T_VARIABLE) {
            $state->maybeDetachAndKillVar($tokens[$start]->text);
        } else {
            $this->killDestructVarsInScan($start, $end, $state);
        }
    }
}
