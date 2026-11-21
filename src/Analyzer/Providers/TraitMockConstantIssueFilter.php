<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Analyzer\Providers;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Reporting\AnnotationKind;

/**
 * Suppresses Mago's native `direct-trait-constant-access` when a constant is accessed
 * through an instance or variable (`$mock::CONST`) rather than the literal trait name (`TraitName::CONST`),
 * because Mockery generates a concrete class using the trait at runtime.
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

        $expr = ltrim(substr($context->contents, $primarySpan->start, $primarySpan->end - $primarySpan->start));
        $after = substr($context->contents, $primarySpan->end, 16);
        $var = null;
        if (preg_match('/^(\$[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)(?:::|$)/', $expr, $match) === 1) {
            if (str_contains($expr, '::') || str_starts_with(ltrim($after), '::')) {
                $var = $match[1];
            }
        }

        if ($var !== null) {
            $prefix = substr($context->contents, 0, $primarySpan->start);
            if (preg_match_all('/\bfunction\b[^{]*\{/', $prefix, $fnMatches, PREG_OFFSET_CAPTURE) >= 1) {
                $lastFn = $fnMatches[0][array_key_last($fnMatches[0])];
                $prefix = substr($prefix, $lastFn[1]);
            }
            $assignPattern = '/' . preg_quote($var, '/') . '\s*=\s*(?:\\\\?(?:Mockery|m)::(?:mock|spy|namedMock|instanceMock)|(?:mock|spy|namedMock)\s*\()/i';
            if (preg_match($assignPattern, $prefix) === 1) {
                return IssueFilterDecision::Remove;
            }
        }

        return IssueFilterDecision::Keep;
    }
}
