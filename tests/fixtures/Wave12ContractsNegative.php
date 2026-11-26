<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery;

final class Wave12ContractsNegative extends Support
{
    public function siblingRegistersAlias(): void
    {
        Mockery::namedMock('Wave12SiblingAlias', Greeter::class);
    }

    public function siblingMustNotSeeAlias(): void
    {
        $fetched = Mockery::fetchMock('Wave12SiblingAlias');
        if ($fetched !== null) {
            $fetched->greet('must-stay-unresolved-across-sibling-methods');
        }
    }

    public function otherClassMustNotSeeSetupAlias(): void
    {
        $fetched = Mockery::fetchMock('Wave12SetupAlias');
        if ($fetched !== null) {
            $fetched->greet('must-stay-unresolved-across-classes');
        }
    }

    public function mentionsBeforeAttributeInBody(): void
    {
        Mockery::namedMock('Wave12AttributeTextAlias', Greeter::class);
        $note = '#[Before]';
        $note . '';
    }

    public function attributeTextMustNotLeakAlias(): void
    {
        $fetched = Mockery::fetchMock('Wave12AttributeTextAlias');
        if ($fetched !== null) {
            $fetched->greet('must-stay-unresolved-when-before-is-only-in-the-body');
        }
    }

    public function plainObjectAsUndefinedMustRemainUnknown(): void
    {
        $box = new GenericBox('hello');
        $box->asUndefined();
    }

    public function sameMethodLaterAliasMustNotResolve(): void
    {
        $fetched = Mockery::fetchMock('DefinedLaterAlias');
        if ($fetched !== null) {
            $fetched->greet('must-stay-unresolved-before-declaration');
        }

        Mockery::namedMock('DefinedLaterAlias', Greeter::class);
    }

    /** @param GenericBox<string>|(Greeter&\Mockery\MockInterface) $maybeMock */
    public function unionWithPlainObjectAsUndefinedMustReport(GenericBox|Greeter $maybeMock): void
    {
        $maybeMock->asUndefined();
    }
}
