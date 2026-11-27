<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;
use Mockery\MockInterface;

final class NamedMockScopingNegative extends Support
{
    public function setUp(): void
    {
        Mockery::namedMock('ShadowedConditionally', Greeter::class);
    }

    public function conditionalShadowMustNotHideLifecycleMismatch(bool $cond): void
    {
        if ($cond) {
            Mockery::namedMock('ShadowedConditionally', ApiClient::class);
        }

        $fetched = Mockery::fetchMock('ShadowedConditionally');
        if ($fetched !== null) {
            $fetched->fetch(1);
        }
    }

    public function closureAliasMustNotLeakOutside(): void
    {
        $fn = static function (): void {
            Mockery::namedMock('InnerOnlyClosureAlias', Greeter::class);
        };
        $fn();

        $fetched = Mockery::fetchMock('InnerOnlyClosureAlias');
        if ($fetched !== null) {
            $fetched->greet('must-not-leak-from-closure');
        }
    }

    public function nullSafeShortCircuitMustNotShadowLifecycle(?Support $helper): void
    {
        $helper?->acceptApiClient(Mockery::namedMock('ShadowedConditionally', ApiClient::class));

        $fetched = Mockery::fetchMock('ShadowedConditionally');
        if ($fetched !== null) {
            $fetched->fetch(1);
        }
    }

    public function coalesceAssignMustNotShadowLifecycle(?MockInterface $slot): void
    {
        $slot ??= Mockery::namedMock('ShadowedConditionally', ApiClient::class);
        unset($slot);

        $fetched = Mockery::fetchMock('ShadowedConditionally');
        if ($fetched !== null) {
            $fetched->fetch(1);
        }
    }
}
