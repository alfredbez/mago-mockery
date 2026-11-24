<?php

declare(strict_types=1);

namespace MockeryFixture\Strict;

use Mockery;

interface SubStrictGreeter extends StrictGreeter
{
    public function farewell(string $name): string;
}

final class Wave9StrictGapsFixture
{
    public function aliasAllowances(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $alias = $mock;
        $alias->shouldAllowMockingMethod('aliasedDynamic');
        $mock->shouldReceive('aliasedDynamic')->andReturn('alias-ok');

        $copyBase = Mockery::mock(StrictGreeter::class);
        $copyOne = $copyBase;
        $copyTwo = $copyOne;
        $copyTwo->shouldAllowMockingMethod('copyOfCopyDynamic');
        $copyBase->shouldReceive('copyOfCopyDynamic')->andReturn('copy-ok');

        $reverseBase = Mockery::mock(StrictGreeter::class);
        $reverseBase->shouldAllowMockingMethod('allowThenAlias');
        $reverseAlias = $reverseBase;
        $reverseAlias->shouldReceive('allowThenAlias')->andReturn('reverse-ok');
    }

    public function reassignmentInvalidation(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldAllowMockingMethod('dynamicAfterReassignment');
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldReceive('dynamicAfterReassignment')->andReturn('x');

        $other = Mockery::mock(StrictGreeter::class);
        $reassigned = Mockery::mock(StrictGreeter::class);
        $reassigned->shouldAllowMockingMethod('dynamicAfterCopyFromOther');
        $reassigned = $other;
        $reassigned->shouldReceive('dynamicAfterCopyFromOther')->andReturn('y');
    }

    /**
     * @param list<int> $items
     */
    public function conditionalAllowances(bool $flag, array $items): void
    {
        $insideIf = Mockery::mock(StrictGreeter::class);
        if ($flag) {
            $insideIf->shouldAllowMockingMethod('dynamicAfterConditional');
        }
        $insideIf->shouldReceive('dynamicAfterConditional')->andReturn('x');

        $beforeIf = Mockery::mock(StrictGreeter::class);
        $beforeIf->shouldAllowMockingMethod('allowedBeforeIf');
        if ($flag) {
            $beforeIf->greet('a');
        }
        $beforeIf->shouldReceive('allowedBeforeIf')->andReturn('ok');

        $bothBranches = Mockery::mock(StrictGreeter::class);
        if ($flag) {
            $bothBranches->shouldAllowMockingMethod('allowedInBothBranches');
        } else {
            $bothBranches->shouldAllowMockingMethod('allowedInBothBranches');
        }
        $bothBranches->shouldReceive('allowedInBothBranches')->andReturn('ok');

        $insideLoop = Mockery::mock(StrictGreeter::class);
        foreach ($items as $_) {
            $insideLoop->shouldAllowMockingMethod('dynamicAfterLoop');
        }
        $insideLoop->shouldReceive('dynamicAfterLoop')->andReturn('z');
    }

    public function higherOrderCalls(): void
    {
        $mock = Mockery::mock(StrictGreeter::class);
        $mock->shouldReceive()->greet('ok')->andReturn('hi');
        $mock->shouldReceive()->missingHigherOrder('bad')->andReturn('nope');

        $sub = Mockery::mock(SubStrictGreeter::class);
        $sub->shouldReceive()->greet('inherited')->andReturn('hi');
        $sub->shouldReceive()->farewell('own')->andReturn('bye');
        $sub->shouldReceive()->missingHigherOrderOnSub('bad')->andReturn('nope');
    }
}
