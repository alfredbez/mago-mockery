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
        if ($expr !== '' && $expr[0] === '$') {
            return IssueFilterDecision::Remove;
        }

        return IssueFilterDecision::Keep;
    }
}
