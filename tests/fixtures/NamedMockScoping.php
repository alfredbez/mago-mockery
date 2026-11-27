<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;

trait NamedMockSetupTrait
{
    public function setUp(): void
    {
        Mockery::namedMock('TraitLifecycleAlias', Greeter::class);
    }

    public function callInsideTrait(): void
    {
        $fetched = Mockery::fetchMock('TraitLifecycleAlias');
        if ($fetched !== null) {
            $fetched->greet('inside-trait');
        }
    }
}

final class NamedMockScoping extends Support
{
    use NamedMockSetupTrait;
    use CrossFileNamedMockTrait;

    public function closureReadsOuterMethodAlias(): void
    {
        Mockery::namedMock('OuterClosureAlias', Greeter::class);

        $fn = static function (): void {
            $fetched = Mockery::fetchMock('OuterClosureAlias');
            if ($fetched !== null) {
                $fetched->greet('from-closure');
            }
        };
        $fn();

        $arrow = static fn (): ?string => Mockery::fetchMock('OuterClosureAlias')?->greet('from-arrow');
        $arrow();
    }

    public function usesTraitSetUpAlias(): void
    {
        $fetched = Mockery::fetchMock('TraitLifecycleAlias');
        if ($fetched !== null) {
            $fetched->greet('from-trait-setup');
            $this->acceptGreeter($fetched);
        }

        $crossFile = Mockery::fetchMock('CrossFileTraitBeforeAlias');
        if ($crossFile !== null) {
            $crossFile->greet('from-cross-file-trait');
            $this->acceptGreeter($crossFile);
        }
    }

    public function unconditionalOverrideShadowsLifecycle(): void
    {
        Mockery::namedMock('TraitLifecycleAlias', ApiClient::class);
        $fetched = Mockery::fetchMock('TraitLifecycleAlias');
        if ($fetched !== null) {
            $fetched->fetch(1);
            $this->acceptApiClient($fetched);
        }
    }

    public function conditionalOverrideNarrowedByInstanceof(bool $cond): void
    {
        if ($cond) {
            Mockery::namedMock('TraitLifecycleAlias', ApiClient::class);
        }

        $fetched = Mockery::fetchMock('TraitLifecycleAlias');
        if ($fetched instanceof ApiClient) {
            $fetched->fetch(1);
        } elseif ($fetched instanceof Greeter) {
            $fetched->greet('narrowed');
        }
    }
}
