<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;
use PhpToken;

use function count;
use function strlen;

/**
 * Suppresses Mago's native `direct-trait-constant-access` when a constant is accessed
 * through a receiver guaranteed on every reachable path to be a Mockery mock object.
 *
 * @internal
 */
final class TraitMockConstantIssueFilter implements IssueFilterHook
{
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

        $tokens = self::tokenizeCode($context->contents);
        if ($tokens === []) {
            return IssueFilterDecision::Keep;
        }

        $receiverRange = self::extractReceiverTokenRange($tokens, $primarySpan->start, $primarySpan->end);
        if ($receiverRange === null) {
            return IssueFilterDecision::Keep;
        }

        [$receiverStart, $receiverEnd] = $receiverRange;
        if (TraitMockFlowAnalyzer::isGuaranteedMockReceiver(
            $tokens,
            $receiverStart,
            $receiverEnd,
            $primarySpan->start,
            $context->codebase,
        )) {
            return IssueFilterDecision::Remove;
        }

        return IssueFilterDecision::Keep;
    }

    /**
     * @return list<PhpToken>
     */
    private static function tokenizeCode(string $contents): array
    {
        $tokens = [];
        foreach (PhpToken::tokenize($contents) as $token) {
            if ($token->id === T_WHITESPACE || $token->id === T_COMMENT || $token->id === T_DOC_COMMENT) {
                continue;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /**
     * @param list<PhpToken> $tokens
     * @return ?array{0: int, 1: int}
     */
    private static function extractReceiverTokenRange(array $tokens, int $spanStart, int $spanEnd): ?array
    {
        $count = count($tokens);
        $first = null;
        $last = null;

        for ($i = 0; $i < $count; ++$i) {
            $tok = $tokens[$i];
            $tokEnd = $tok->pos + strlen($tok->text);
            if ($tokEnd <= $spanStart) {
                continue;
            }
            if ($tok->pos >= $spanEnd) {
                break;
            }
            $first ??= $i;
            $last = $i + 1;
        }

        if ($first === null || $last === null) {
            return null;
        }

        for ($i = $first; $i < $last; ++$i) {
            $tok = $tokens[$i];
            if ($tok->text === '(') {
                $i = TraitMockFlowAnalyzer::findMatchingClose($tokens, $i, '(', ')');
            } elseif ($tok->text === '[' || $tok->id === T_ATTRIBUTE) {
                $i = TraitMockFlowAnalyzer::findMatchingClose($tokens, $i, '[', ']');
            } elseif ($tok->text === '{' || $tok->id === T_CURLY_OPEN || $tok->id === T_DOLLAR_OPEN_CURLY_BRACES) {
                $i = TraitMockFlowAnalyzer::findMatchingClose($tokens, $i, '{', '}');
            } elseif ($tok->id === T_DOUBLE_COLON) {
                return $i > $first ? [$first, $i] : null;
            }
        }

        if ($last < $count && $tokens[$last]->id === T_DOUBLE_COLON) {
            return [$first, $last];
        }

        return null;
    }
}
