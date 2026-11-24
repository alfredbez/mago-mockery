<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;

use function array_key_last;
use function ltrim;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Suppresses Mago's native `direct-trait-constant-access` when a constant is accessed
 * through a Mockery trait mock (variable, container mock, or parenthesized factory expression)
 * rather than the literal trait name or a plain class-string variable.
 */
final class TraitMockConstantIssueFilter implements IssueFilterHook
{
    private const FACTORY_CALL_PREFIX = '/^(?:\\\\?(?:Mockery|m)::(?:mock|spy|namedMock|instanceMock)|(?:mock|spy|namedMock)|\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:->[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\([^()]*\))?)*->(?:mock|spy|namedMock|instanceMock)|\\\\?(?:Mockery|m)::getContainer\s*\(\s*\)\s*->\s*(?:mock|spy|namedMock|instanceMock))\s*\(/i';

    public function getCodes(): array
    {
        return ['direct-trait-constant-access'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $primarySpan = null;
        foreach ($context->issue->annotations as $annotation) {
            if ($annotation->kind === AnnotationKind::Primary) {
                $primarySpan = $annotation->span;
                break;
            }
        }
        $primarySpan ??= $context->issue->annotations[0]->span ?? null;
        if ($primarySpan === null) {
            return IssueFilterDecision::Keep;
        }

        $rawExpr = trim(substr($context->contents, $primarySpan->start, $primarySpan->end - $primarySpan->start));
        $after = ltrim(substr($context->contents, $primarySpan->end, 16));

        $targetExpr = self::extractConstantReceiver($rawExpr, $after);
        if ($targetExpr === null) {
            return IssueFilterDecision::Keep;
        }

        $unwrapped = self::stripOuterParens($targetExpr);
        if (self::isFactoryCallExpression($unwrapped)) {
            return IssueFilterDecision::Remove;
        }

        if (preg_match('/^\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/', $unwrapped) === 1) {
            $prefix = substr($context->contents, 0, $primarySpan->start);
            if (self::isLatestAssignmentMockInScope($prefix, $unwrapped)) {
                return IssueFilterDecision::Remove;
            }
        }

        return IssueFilterDecision::Keep;
    }

    private static function extractConstantReceiver(string $expr, string $after): ?string
    {
        if ($expr === '') {
            return null;
        }

        $pos = self::findTopLevelDoubleColon($expr);
        if ($pos !== null) {
            return trim(substr($expr, 0, $pos));
        }

        if (str_starts_with($after, '::')) {
            return $expr;
        }

        return null;
    }

    private static function findTopLevelDoubleColon(string $expr): ?int
    {
        $depth = 0;
        $len = strlen($expr);
        $inSingle = false;
        $inDouble = false;
        for ($i = 0; $i < $len - 1; ++$i) {
            $ch = $expr[$i];
            if ($inSingle) {
                if ($ch === '\\') {
                    ++$i;
                } elseif ($ch === "'") {
                    $inSingle = false;
                }
                continue;
            }
            if ($inDouble) {
                if ($ch === '\\') {
                    ++$i;
                } elseif ($ch === '"') {
                    $inDouble = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inSingle = true;
            } elseif ($ch === '"') {
                $inDouble = true;
            } elseif ($ch === '(' || $ch === '[' || $ch === '{') {
                ++$depth;
            } elseif ($ch === ')' || $ch === ']' || $ch === '}') {
                if ($depth > 0) {
                    --$depth;
                }
            } elseif ($depth === 0 && $ch === ':' && $expr[$i + 1] === ':') {
                return $i;
            }
        }

        return null;
    }

    private static function stripOuterParens(string $expr): string
    {
        $expr = trim($expr);
        while (strlen($expr) >= 2 && $expr[0] === '(' && $expr[-1] === ')') {
            $depth = 0;
            $wrapsWhole = true;
            $len = strlen($expr);
            for ($i = 0; $i < $len - 1; ++$i) {
                if ($expr[$i] === '(') {
                    ++$depth;
                } elseif ($expr[$i] === ')') {
                    --$depth;
                    if ($depth === 0) {
                        $wrapsWhole = false;
                        break;
                    }
                }
            }
            if (!$wrapsWhole) {
                break;
            }
            $expr = trim(substr($expr, 1, -1));
        }

        return $expr;
    }

    private static function isFactoryCallExpression(string $expr): bool
    {
        return preg_match(self::FACTORY_CALL_PREFIX, ltrim($expr)) === 1;
    }

    /**
     * Checks whether the most recent assignment to `$var` in the enclosing function scope
     * produces a Mockery mock (directly or through a plain variable alias), invalidating
     * stale mock assignments once `$var` is reassigned to anything else.
     */
    private static function isLatestAssignmentMockInScope(string $prefix, string $var): bool
    {
        $clean = self::stripComments($prefix);
        if (preg_match_all('/\bfunction\b[^{]*\{/', $clean, $fnMatches, PREG_OFFSET_CAPTURE) >= 1) {
            $lastFn = $fnMatches[0][array_key_last($fnMatches[0])];
            $clean = substr($clean, $lastFn[1]);
        }

        $visited = [];
        $currentVar = $var;
        $cursor = $clean;
        while (!isset($visited[$currentVar])) {
            $visited[$currentVar] = true;
            $pattern = '/(?<![A-Za-z0-9_])' . preg_quote($currentVar, '/') . '\s*=(?!=)\s*([^;]+)/';
            if (preg_match_all($pattern, $cursor, $matches, PREG_OFFSET_CAPTURE) < 1) {
                return false;
            }

            $lastIndex = array_key_last($matches[1]);
            $rhs = self::stripOuterParens(trim($matches[1][$lastIndex][0]));
            if (self::isFactoryCallExpression($rhs)) {
                return true;
            }

            if (preg_match('/^(\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)$/', $rhs, $aliasMatch) === 1) {
                $assignOffset = $matches[0][$lastIndex][1];
                $cursor = substr($cursor, 0, $assignOffset);
                $currentVar = $aliasMatch[1];
                continue;
            }

            return false;
        }

        return false;
    }

    private static function stripComments(string $code): string
    {
        $out = '';
        $len = strlen($code);
        $i = 0;
        while ($i < $len) {
            $ch = $code[$i];
            $next = $i + 1 < $len ? $code[$i + 1] : '';

            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $out .= $quote;
                ++$i;
                while ($i < $len) {
                    $c = $code[$i];
                    ++$i;
                    if ($c === '\\' && $i < $len) {
                        ++$i;
                    } elseif ($c === $quote) {
                        break;
                    }
                }
                $out .= $quote;
                continue;
            }

            if ($ch === '/' && $next === '/') {
                $i += 2;
                while ($i < $len && $code[$i] !== "\n") {
                    ++$i;
                }
                continue;
            }

            if ($ch === '#' && $next !== '[') {
                ++$i;
                while ($i < $len && $code[$i] !== "\n") {
                    ++$i;
                }
                continue;
            }

            if ($ch === '/' && $next === '*') {
                $i += 2;
                while ($i < $len - 1 && !($code[$i] === '*' && $code[$i + 1] === '/')) {
                    ++$i;
                }
                $i += 2;
                continue;
            }

            $out .= $ch;
            ++$i;
        }

        return rtrim($out);
    }
}
