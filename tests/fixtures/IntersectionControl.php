<?php

declare(strict_types=1);

namespace MockeryFixture;

/**
 * Control group: plain PHP intersections without Mockery.
 *
 * Both analyzer runs must report the same issue here. A difference would mean
 * the extension changed intersection handling globally.
 */
interface Alpha
{
    public function alpha(): void;
}

interface Beta
{
    public function beta(): void;
}

final class OnlyAlpha implements Alpha
{
    public function alpha(): void {}
}

final class AlphaBeta implements Alpha, Beta
{
    public function alpha(): void {}

    public function beta(): void {}
}

final class IntersectionControl
{
    public function accepts(Alpha&Beta $value): void
    {
        $value->alpha();
    }

    public function positive(): void
    {
        $this->accepts(new AlphaBeta());
    }

    public function negative(): void
    {
        $this->accepts(new OnlyAlpha());
    }
}
