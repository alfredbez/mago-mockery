<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Closure;
use PhpToken;

use function array_pop;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function max;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;

/**
 * @phpstan-type ImportMap array{
 *     namespace: string,
 *     classes: array<string, string>,
 *     functions: array<string, string>
 * }
 * @phpstan-type ScopeInfo array{
 *     bodyStart: int,
 *     bodyEnd: int,
 *     paramsStart: ?int,
 *     paramsEnd: ?int,
 *     enclosingClass: ?string
 * }
 *
 * @internal
 */
final class TraitMockTokenScope
{
    private const MODIFIER_TOKENS = [
        T_PUBLIC => true, T_PROTECTED => true, T_PRIVATE => true,
        T_STATIC => true, T_FINAL => true, T_ABSTRACT => true, T_READONLY => true,
    ];

    private const CLASSLIKE_TOKENS = [
        T_CLASS => true, T_INTERFACE => true, T_TRAIT => true, T_ENUM => true,
    ];

    private const COMPOUND_ASSIGN_TOKENS = [
        T_PLUS_EQUAL => true, T_MINUS_EQUAL => true, T_MUL_EQUAL => true, T_DIV_EQUAL => true,
        T_MOD_EQUAL => true, T_POW_EQUAL => true, T_CONCAT_EQUAL => true, T_AND_EQUAL => true,
        T_OR_EQUAL => true, T_XOR_EQUAL => true, T_SL_EQUAL => true, T_SR_EQUAL => true, T_COALESCE_EQUAL => true,
    ];

    private const BINARY_OPERATOR_TOKENS = [
        T_IS_EQUAL => true, T_IS_NOT_EQUAL => true, T_IS_IDENTICAL => true, T_IS_NOT_IDENTICAL => true,
        T_IS_SMALLER_OR_EQUAL => true, T_IS_GREATER_OR_EQUAL => true, T_SPACESHIP => true,
        T_SL => true, T_SR => true, T_POW => true, T_INSTANCEOF => true,
    ];

    private const BINARY_OPERATOR_TEXTS = [
        '+' => true, '-' => true, '*' => true, '/' => true, '%' => true,
        '.' => true, '<' => true, '>' => true, '&' => true, '|' => true, '^' => true,
    ];

    public readonly int $count;

    /** @var array<int, int> */
    public readonly array $close;

    /** @var array<int, int> */
    public readonly array $open;

    /**
     * @param list<PhpToken> $tokens
     * @param ImportMap $imports
     */
    public function __construct(
        public readonly array $tokens,
        public readonly int $targetPos,
        public readonly array $imports,
    ) {
        $this->count = count($tokens);
        [$this->close, $this->open] = self::buildBracketMaps($tokens);
    }

    /**
     * @param list<PhpToken> $tokens
     */
    public static function findMatchingClose(array $tokens, int $openIdx, string $openChar, string $closeChar): int
    {
        $count = count($tokens);
        $depth = 0;
        for ($i = $openIdx; $i < $count; ++$i) {
            $tok = $tokens[$i];
            $isOpen = $openChar === '{'
                ? self::isOpenBrace($tok)
                : ($openChar === '[' ? ($tok->text === '[' || $tok->id === T_ATTRIBUTE) : $tok->text === $openChar);
            if ($isOpen) {
                ++$depth;
            } elseif ($tok->text === $closeChar) {
                --$depth;
            }
            if ($depth === 0) {
                return $i;
            }
        }

        return $count - 1;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return ImportMap
     */
    public static function collectImports(array $tokens, int $limit): array
    {
        $namespace = '';
        $classes = [];
        $functions = [];
        $braceDepth = 0;

        for ($i = 0; $i < $limit; ++$i) {
            $tok = $tokens[$i];
            if (self::isOpenBrace($tok)) {
                ++$braceDepth;
                continue;
            }
            if ($tok->text === '}') {
                $braceDepth = max(0, $braceDepth - 1);
                continue;
            }
            if ($braceDepth === 0 && $tok->id === T_NAMESPACE && $i + 1 < $limit) {
                if ($tokens[$i + 1]->id === T_NS_SEPARATOR) {
                    continue;
                }
                $namespace = self::isNameToken($tokens[$i + 1]) ? ltrim($tokens[$i + 1]->text, '\\') : '';
                $classes = [];
                $functions = [];
                while ($i < $limit && $tokens[$i]->text !== ';' && $tokens[$i]->text !== '{') {
                    ++$i;
                }
                continue;
            }
            if ($braceDepth <= 1 && $tok->id === T_USE && $i + 1 < $limit && $tokens[$i + 1]->text !== '(') {
                if ($braceDepth === 1 && $namespace === '') {
                    continue;
                }
                $i = self::parseUseStatement($tokens, $i + 1, $limit, $classes, $functions);
            }
        }

        return ['namespace' => $namespace, 'classes' => $classes, 'functions' => $functions];
    }

    /**
     * @param list<PhpToken> $tokens
     * @param ImportMap $imports
     * @return ScopeInfo
     */
    public static function findEnclosingNamedFunctionOrFileScope(array $tokens, int $targetIndex, array $imports): array
    {
        $count = count($tokens);
        /** @var list<array{isNamedFn: bool, bodyStart: int, paramsStart: ?int, paramsEnd: ?int, className: ?string, isClassFrame: bool}> $stack */
        $stack = [];
        $pendingFn = null;
        $pendingClass = null;
        $pendingIsClass = false;

        for ($i = 0; $i < $targetIndex; ++$i) {
            $tok = $tokens[$i];
            if (isset(self::CLASSLIKE_TOKENS[$tok->id]) && !self::isMemberAccess($tokens, $i) && ($i === 0 || $tokens[$i - 1]->id !== T_NEW)) {
                $pendingIsClass = true;
                $pendingClass = ($i + 1 < $count && $tokens[$i + 1]->id === T_STRING)
                    ? self::resolveClassName($tokens[$i + 1], $imports)
                    : null;
                continue;
            }
            if ($tok->id === T_NEW && $i + 1 < $count && $tokens[$i + 1]->id === T_CLASS) {
                $pendingIsClass = true;
                $pendingClass = null;
                ++$i;
                continue;
            }
            if ($tok->id === T_FUNCTION && !self::isMemberAccess($tokens, $i)) {
                $j = $i + 1 + ($i + 1 < $count && $tokens[$i + 1]->text === '&' ? 1 : 0);
                $isNamed = $j < $count && $tokens[$j]->text !== '(';
                while ($j < $count && $tokens[$j]->text !== '(' && $tokens[$j]->text !== '{' && $tokens[$j]->text !== ';') {
                    ++$j;
                }
                if ($j < $count && $tokens[$j]->text === '(') {
                    $paramsEnd = self::findMatchingClose($tokens, $j, '(', ')');
                    $pendingFn = ['isNamedFn' => $isNamed, 'paramsStart' => $j + 1, 'paramsEnd' => $paramsEnd];
                    if ($paramsEnd < $targetIndex) {
                        $i = $paramsEnd;
                    }
                }
                continue;
            }
            if ($tok->text === ';' && $pendingFn !== null) {
                $pendingFn = null;
                continue;
            }
            if (self::isOpenBrace($tok)) {
                if ($tok->text === '{' && $pendingFn !== null) {
                    $stack[] = ['isNamedFn' => $pendingFn['isNamedFn'], 'bodyStart' => $i + 1, 'paramsStart' => $pendingFn['paramsStart'], 'paramsEnd' => $pendingFn['paramsEnd'], 'className' => null, 'isClassFrame' => false];
                    $pendingFn = null;
                } elseif ($tok->text === '{' && $pendingIsClass) {
                    $stack[] = ['isNamedFn' => false, 'bodyStart' => $i + 1, 'paramsStart' => null, 'paramsEnd' => null, 'className' => $pendingClass, 'isClassFrame' => true];
                    $pendingIsClass = false;
                    $pendingClass = null;
                } else {
                    $stack[] = ['isNamedFn' => false, 'bodyStart' => $i + 1, 'paramsStart' => null, 'paramsEnd' => null, 'className' => null, 'isClassFrame' => false];
                }
                continue;
            }
            if ($tok->text === '}' && $stack !== []) {
                array_pop($stack);
            }
        }

        $enclosingClass = null;
        for ($s = count($stack) - 1; $s >= 0; --$s) {
            if ($stack[$s]['isClassFrame']) {
                $enclosingClass = $stack[$s]['className'];
                break;
            }
        }
        for ($s = count($stack) - 1; $s >= 0; --$s) {
            if ($stack[$s]['isNamedFn']) {
                return [
                    'bodyStart' => $stack[$s]['bodyStart'],
                    'bodyEnd' => self::findMatchingClose($tokens, $stack[$s]['bodyStart'] - 1, '{', '}'),
                    'paramsStart' => $stack[$s]['paramsStart'],
                    'paramsEnd' => $stack[$s]['paramsEnd'],
                    'enclosingClass' => $enclosingClass,
                ];
            }
        }

        return ['bodyStart' => 0, 'bodyEnd' => $count, 'paramsStart' => null, 'paramsEnd' => null, 'enclosingClass' => $enclosingClass];
    }

    public function closeOf(int $openIdx): int
    {
        return $this->close[$openIdx] ?? ($this->count - 1);
    }

    public function openOf(int $closeIdx, int $floor): int
    {
        return max($floor, $this->open[$closeIdx] ?? $floor);
    }

    /** @return array{0: int, 1: int} */
    public function stripOuterParens(int $start, int $end): array
    {
        while ($start < $end && $this->tokens[$start]->text === '(' && ($this->close[$start] ?? -1) === $end - 1) {
            ++$start;
            --$end;
        }

        return [$start, $end];
    }

    public function rangeContainsTarget(int $start, int $end): bool
    {
        if ($start >= $end || $start < 0 || $end > $this->count) {
            return false;
        }
        $last = $this->tokens[$end - 1];

        return $this->targetPos >= $this->tokens[$start]->pos && $this->targetPos < $last->pos + strlen($last->text);
    }

    public function findStatementEnd(int $start, int $limit): int
    {
        $tok = $this->tokens[$start];
        if ($tok->text === '{') {
            return $this->closeOf($start) + 1;
        }
        if ($tok->id === T_IF) {
            $i = $start + 1;
            if ($i < $limit && $this->tokens[$i]->text === '(') {
                $i = $this->closeOf($i) + 1;
            }
            if ($i < $limit && $this->tokens[$i]->text === ':') {
                return $this->scanToStatementTerminator($this->findColonBlockEnd($i + 1, $limit, [T_ENDIF]) + 1, $limit);
            }
            $i = $this->findStatementEnd($i, $limit);
            while ($i < $limit) {
                if ($this->tokens[$i]->id === T_ELSEIF) {
                    $i = ($i + 1 < $limit && $this->tokens[$i + 1]->text === '(') ? $this->closeOf($i + 1) + 1 : $i + 1;
                    $i = $this->findStatementEnd($i, $limit);
                    continue;
                }
                if ($this->tokens[$i]->id === T_ELSE) {
                    $i = $this->findStatementEnd($i + 1, $limit);
                }
                break;
            }

            return $i;
        }
        if ($tok->id === T_TRY) {
            $i = ($start + 1 < $limit && $this->tokens[$start + 1]->text === '{') ? $this->closeOf($start + 1) + 1 : $start + 1;
            while ($i < $limit && ($this->tokens[$i]->id === T_CATCH || $this->tokens[$i]->id === T_FINALLY)) {
                $k = ($i + 1 < $limit && $this->tokens[$i + 1]->text === '(') ? $this->closeOf($i + 1) + 1 : $i + 1;
                if ($k < $limit && $this->tokens[$k]->text === '{') {
                    $i = $this->closeOf($k) + 1;
                    continue;
                }
                break;
            }

            return $i;
        }
        if ($tok->id === T_FOR || $tok->id === T_FOREACH || $tok->id === T_WHILE || $tok->id === T_SWITCH) {
            $i = ($start + 1 < $limit && $this->tokens[$start + 1]->text === '(') ? $this->closeOf($start + 1) + 1 : $start + 1;
            if ($i < $limit && $this->tokens[$i]->text === ':') {
                $endTok = match ($tok->id) {
                    T_FOR => T_ENDFOR,
                    T_FOREACH => T_ENDFOREACH,
                    T_WHILE => T_ENDWHILE,
                    default => T_ENDSWITCH,
                };

                return $this->scanToStatementTerminator($this->findColonBlockEnd($i + 1, $limit, [$endTok]) + 1, $limit);
            }

            return $this->findStatementEnd($i, $limit);
        }
        if ($tok->id === T_DO) {
            $i = $this->findStatementEnd($start + 1, $limit);

            return ($i < $limit && $this->tokens[$i]->id === T_WHILE) ? $this->scanToStatementTerminator($i + 1, $limit) : $i;
        }
        if ($this->isSkippedDeclaration($start, $limit)) {
            for ($i = $start; $i < $limit; ++$i) {
                $t = $this->tokens[$i];
                if ($t->text === ';') {
                    return $i + 1;
                }
                if ($t->text === '{') {
                    return $this->closeOf($i) + 1;
                }
                if (isset($this->close[$i])) {
                    $i = $this->close[$i];
                }
            }

            return $limit;
        }

        return $this->scanToStatementTerminator($start, $limit);
    }

    /** @param list<int> $stopTokens */
    public function findColonBlockEnd(int $start, int $limit, array $stopTokens): int
    {
        $depth = 0;
        for ($i = $start; $i < $limit; ++$i) {
            $id = $this->tokens[$i]->id;
            if ($id === T_IF || $id === T_FOR || $id === T_FOREACH || $id === T_WHILE || $id === T_SWITCH) {
                $k = ($i + 1 < $limit && $this->tokens[$i + 1]->text === '(') ? $this->closeOf($i + 1) + 1 : $i + 1;
                if ($k < $limit && $this->tokens[$k]->text === ':') {
                    ++$depth;
                    $i = $k;
                }
                continue;
            }
            if ($depth === 0 && in_array($id, $stopTokens, true)) {
                return $i;
            }
            if ($id === T_ENDIF || $id === T_ENDFOR || $id === T_ENDFOREACH || $id === T_ENDWHILE || $id === T_ENDSWITCH) {
                $depth = max(0, $depth - 1);
            }
        }

        return $limit;
    }

    public function scanToStatementTerminator(int $start, int $limit): int
    {
        for ($i = $start; $i < $limit; ++$i) {
            $tok = $this->tokens[$i];
            if ($tok->text === ';' || $tok->id === T_CLOSE_TAG) {
                return $i + 1;
            }
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
            }
        }

        return $limit;
    }

    public function trimTerminator(int $end): int
    {
        return ($end > 0 && ($this->tokens[$end - 1]->text === ';' || $this->tokens[$end - 1]->id === T_CLOSE_TAG))
            ? $end - 1
            : $end;
    }

    public function isGotoLabel(int $index, int $limit): bool
    {
        return $this->tokens[$index]->id === T_STRING && $index + 1 < $limit && $this->tokens[$index + 1]->text === ':';
    }

    public function isSkippedDeclaration(int $start, int $limit): bool
    {
        $i = $start;
        while ($i < $limit && $this->tokens[$i]->id === T_ATTRIBUTE) {
            $i = $this->closeOf($i) + 1;
        }
        while ($i < $limit && isset(self::MODIFIER_TOKENS[$this->tokens[$i]->id])) {
            ++$i;
        }
        if ($i >= $limit) {
            return false;
        }
        $id = $this->tokens[$i]->id;
        if (isset(self::CLASSLIKE_TOKENS[$id]) || $id === T_NAMESPACE || $id === T_USE || $id === T_CONST || $id === T_DECLARE) {
            return $id !== T_NAMESPACE || ($i + 1 < $limit && $this->tokens[$i + 1]->id !== T_NS_SEPARATOR);
        }
        if ($id === T_FUNCTION) {
            $j = $i + 1 + ($i + 1 < $limit && $this->tokens[$i + 1]->text === '&' ? 1 : 0);

            return $j < $limit && $this->tokens[$j]->text !== '(';
        }

        return false;
    }

    public function isClosureStart(int $start, int $end): bool
    {
        $i = $start + ($this->tokens[$start]->id === T_STATIC ? 1 : 0);
        if ($i >= $end || $this->tokens[$i]->id !== T_FUNCTION) {
            return false;
        }
        $i += 1 + ($i + 1 < $end && $this->tokens[$i + 1]->text === '&' ? 1 : 0);
        if ($i >= $end || $this->tokens[$i]->text !== '(') {
            return false;
        }
        $j = $this->closeOf($i) + 1;
        while ($j < $end && $this->tokens[$j]->text !== '{') {
            $j = isset($this->close[$j]) ? $this->close[$j] + 1 : $j + 1;
        }

        return $j < $end && $this->closeOf($j) === $end - 1;
    }

    public function isArrowFunctionStart(int $start, int $end): bool
    {
        $i = $start + ($this->tokens[$start]->id === T_STATIC ? 1 : 0);

        return $i < $end && $this->tokens[$i]->id === T_FN;
    }

    public function findArrowBodyEnd(int $start, int $limit): int
    {
        for ($i = $start; $i < $limit; ++$i) {
            $t = $this->tokens[$i]->text;
            if ($t === ';' || $t === ',' || $t === ')' || $t === ']' || $t === '}') {
                return $i;
            }
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
            }
        }

        return $limit;
    }

    public function isAssignableLhs(int $start, int $assignIdx): bool
    {
        [$start, $assignIdx] = $this->stripOuterParens($start, $assignIdx);
        if ($start >= $assignIdx) {
            return false;
        }
        $first = $this->tokens[$start];
        if ($first->text === '[') {
            return $this->closeOf($start) === $assignIdx - 1;
        }
        if ($first->id === T_LIST && $start + 1 < $assignIdx && $this->tokens[$start + 1]->text === '(') {
            return $this->closeOf($start + 1) === $assignIdx - 1;
        }
        for ($i = $start; $i < $assignIdx; ++$i) {
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
                continue;
            }
            $tok = $this->tokens[$i];
            if (
                $tok->id === T_VARIABLE || $tok->text === '$'
                || $tok->id === T_OBJECT_OPERATOR || $tok->id === T_DOUBLE_COLON
                || self::isNameToken($tok) || self::isKeywordIdentifier($tok)
            ) {
                continue;
            }

            return false;
        }

        return true;
    }

    /** @param Closure(PhpToken, int): bool $predicate */
    public function findTopLevel(int $start, int $end, Closure $predicate): ?int
    {
        for ($i = $start; $i < $end; ++$i) {
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
                continue;
            }
            if ($predicate($this->tokens[$i], $i)) {
                return $i;
            }
        }

        return null;
    }

    /** @param list<int> $tokenIds */
    public function findTopLevelId(int $start, int $end, array $tokenIds): ?int
    {
        return $this->findTopLevel($start, $end, static fn (PhpToken $t): bool => in_array($t->id, $tokenIds, true));
    }

    public function findTopLevelAssignment(int $start, int $end): ?int
    {
        return $this->findTopLevel(
            $start,
            $end,
            static fn (PhpToken $t): bool => $t->text === '=' || isset(self::COMPOUND_ASSIGN_TOKENS[$t->id]),
        );
    }

    /** @return ?array{0: int, 1: int} */
    public function findTopLevelTernary(int $start, int $end): ?array
    {
        $qIdx = null;
        $qDepth = 0;
        for ($i = $start; $i < $end; ++$i) {
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
                continue;
            }
            $text = $this->tokens[$i]->text;
            if ($text === '?') {
                if ($qIdx === null) {
                    $qIdx = $i;
                } else {
                    ++$qDepth;
                }
            } elseif ($text === ':' && $qIdx !== null) {
                if ($qDepth === 0) {
                    return [$qIdx, $i];
                }
                --$qDepth;
            }
        }

        return null;
    }

    public function findTopLevelBinaryOperator(int $start, int $end): ?int
    {
        return $this->findTopLevel(
            $start,
            $end,
            fn (PhpToken $t, int $i): bool => $i > $start
                && (isset(self::BINARY_OPERATOR_TOKENS[$t->id]) || isset(self::BINARY_OPERATOR_TEXTS[$t->text]))
                && self::canEndExpression($this->tokens[$i - 1]),
        );
    }

    /** @return list<array{0: int, 1: int}> */
    public function splitTopLevel(int $start, int $end, string $delimiter): array
    {
        $parts = [];
        $partStart = $start;
        for ($i = $start; $i < $end; ++$i) {
            if (isset($this->close[$i])) {
                $i = $this->close[$i];
                continue;
            }
            if ($this->tokens[$i]->text === $delimiter) {
                $parts[] = [$partStart, $i];
                $partStart = $i + 1;
            }
        }
        if ($partStart < $end) {
            $parts[] = [$partStart, $end];
        }

        return $parts;
    }

    public static function isOpenBrace(PhpToken $token): bool
    {
        return $token->text === '{' || $token->id === T_CURLY_OPEN || $token->id === T_DOLLAR_OPEN_CURLY_BRACES;
    }

    /** @param list<PhpToken> $tokens */
    public static function isMemberAccess(array $tokens, int $index): bool
    {
        if ($index <= 0) {
            return false;
        }
        $prevId = $tokens[$index - 1]->id;

        return $prevId === T_OBJECT_OPERATOR || $prevId === T_NULLSAFE_OBJECT_OPERATOR || $prevId === T_DOUBLE_COLON;
    }

    public static function canEndExpression(PhpToken $token): bool
    {
        return $token->id === T_VARIABLE || $token->id === T_LNUMBER || $token->id === T_DNUMBER
            || $token->id === T_CONSTANT_ENCAPSED_STRING || $token->id === T_INC || $token->id === T_DEC
            || $token->text === ')' || $token->text === ']' || $token->text === '}' || $token->text === '"'
            || self::isNameToken($token);
    }

    public static function isNameToken(PhpToken $token): bool
    {
        return $token->id === T_STRING || $token->id === T_NAME_QUALIFIED
            || $token->id === T_NAME_FULLY_QUALIFIED || $token->id === T_NAME_RELATIVE;
    }

    public static function isKeywordIdentifier(PhpToken $token): bool
    {
        return $token->id >= 256 && $token->text !== ''
            && (($token->text[0] >= 'a' && $token->text[0] <= 'z') || ($token->text[0] >= 'A' && $token->text[0] <= 'Z'));
    }

    /** @param ImportMap $imports */
    public static function resolveClassName(PhpToken $token, array $imports): string
    {
        $text = $token->text;
        if ($token->id === T_NAME_FULLY_QUALIFIED) {
            return ltrim($text, '\\');
        }
        if ($token->id === T_NAME_RELATIVE) {
            $rest = substr($text, strlen('namespace\\'));

            return $imports['namespace'] === '' ? $rest : $imports['namespace'] . '\\' . $rest;
        }
        if ($token->id === T_NAME_QUALIFIED) {
            $parts = explode('\\', $text, 2);
            $head = strtolower($parts[0]);
            if (isset($imports['classes'][$head])) {
                return $imports['classes'][$head] . '\\' . $parts[1];
            }

            return $imports['namespace'] === '' ? $text : $imports['namespace'] . '\\' . $text;
        }
        $lower = strtolower($text);
        if (isset($imports['classes'][$lower])) {
            return $imports['classes'][$lower];
        }

        return $imports['namespace'] === '' ? $text : $imports['namespace'] . '\\' . $text;
    }

    public function resolveGlobalFunctionCandidate(PhpToken $token): string
    {
        if ($token->id === T_NAME_FULLY_QUALIFIED) {
            return ltrim($token->text, '\\');
        }
        if ($token->id === T_STRING) {
            return $this->imports['functions'][strtolower($token->text)] ?? $token->text;
        }

        return ltrim($token->text, '\\');
    }

    /**
     * @param list<PhpToken> $tokens
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private static function buildBracketMaps(array $tokens): array
    {
        $close = [];
        $open = [];
        /** @var list<array{0: int, 1: string}> $stack */
        $stack = [];
        foreach ($tokens as $i => $tok) {
            if ($tok->text === '(') {
                $stack[] = [$i, ')'];
            } elseif ($tok->text === '[' || $tok->id === T_ATTRIBUTE) {
                $stack[] = [$i, ']'];
            } elseif (self::isOpenBrace($tok)) {
                $stack[] = [$i, '}'];
            } elseif (($tok->text === ')' || $tok->text === ']' || $tok->text === '}') && $stack !== []) {
                $top = $stack[count($stack) - 1];
                if ($top[1] === $tok->text) {
                    array_pop($stack);
                    $close[$top[0]] = $i;
                    $open[$i] = $top[0];
                }
            }
        }

        return [$close, $open];
    }

    /**
     * @param list<PhpToken> $tokens
     * @param array<string, string> $classes
     * @param array<string, string> $functions
     */
    private static function parseUseStatement(array $tokens, int $i, int $limit, array &$classes, array &$functions): int
    {
        $kind = 'class';
        if ($i < $limit && $tokens[$i]->id === T_FUNCTION) {
            $kind = 'function';
            ++$i;
        } elseif ($i < $limit && $tokens[$i]->id === T_CONST) {
            $kind = 'const';
            ++$i;
        }
        $prefix = '';
        while ($i < $limit && $tokens[$i]->text !== ';') {
            $entryKind = $kind;
            if ($tokens[$i]->id === T_FUNCTION) {
                $entryKind = 'function';
                ++$i;
            } elseif ($tokens[$i]->id === T_CONST) {
                $entryKind = 'const';
                ++$i;
            }
            if ($i >= $limit || !self::isNameToken($tokens[$i])) {
                ++$i;
                continue;
            }
            $name = ltrim($tokens[$i]->text, '\\');
            ++$i;
            if ($i < $limit && $tokens[$i]->text === '{') {
                $prefix = $name . '\\';
                ++$i;
                continue;
            }
            $fqn = $prefix . $name;
            $alias = self::shortName($fqn);
            if ($i + 1 < $limit && $tokens[$i]->id === T_AS && self::isNameToken($tokens[$i + 1])) {
                $alias = $tokens[$i + 1]->text;
                $i += 2;
            }
            if ($entryKind === 'class') {
                $classes[strtolower($alias)] = ltrim($fqn, '\\');
            } elseif ($entryKind === 'function') {
                $functions[strtolower($alias)] = ltrim($fqn, '\\');
            }
            if ($i < $limit && $tokens[$i]->text === '}') {
                $prefix = '';
                ++$i;
            } elseif ($i < $limit && $tokens[$i]->text === ',') {
                ++$i;
            }
        }

        return $i;
    }

    private static function shortName(string $fqn): string
    {
        if (!str_contains($fqn, '\\')) {
            return $fqn;
        }
        $parts = explode('\\', $fqn);

        return $parts[count($parts) - 1];
    }
}
