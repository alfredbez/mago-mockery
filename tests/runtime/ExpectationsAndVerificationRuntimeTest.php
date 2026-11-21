<?php

declare(strict_types=1);

namespace AlfredBez\MagoMockery\Runtime;

use InvalidArgumentException;
use Mockery;
use Mockery\CompositeExpectation;
use Mockery\Exception\InvalidCountException;
use Mockery\ExpectsHigherOrderMessage;
use Mockery\HigherOrderMessage;
use Mockery\Matcher\Any;
use Mockery\Matcher\Closure as ClosureMatcher;
use Mockery\Matcher\Contains;
use Mockery\Matcher\HasKey;
use Mockery\Matcher\HasValue;
use Mockery\Matcher\MustBe;
use Mockery\Matcher\Subset;
use Mockery\Matcher\Type as TypeMatcher;
use Mockery\VerificationDirector;
use PHPUnit\Framework\TestCase;

interface Recorder
{
    public function record(string $name, mixed $payload = null): mixed;

    public function chain(): self;
}

interface InvokableHandler
{
    public function __invoke(string $event): void;
}

final class ExpectationsAndVerificationRuntimeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testAllowsAndExpectsReturnTypesMatchAnalyzerContract(): void
    {
        $mapMock = Mockery::mock(Recorder::class);
        $fromMap = $mapMock->allows(['record' => 'mapped']);
        self::assertSame($mapMock, $fromMap);
        self::assertSame('mapped', $mapMock->record('any'));

        $stringMock = Mockery::mock(Recorder::class);
        $fromString = $stringMock->allows('record');
        self::assertInstanceOf(CompositeExpectation::class, $fromString);
        $fromString->andReturn('allowed');
        self::assertSame('allowed', $stringMock->record('any'));

        $fromEmptyArray = $stringMock->allows([]);
        self::assertInstanceOf(HigherOrderMessage::class, $fromEmptyArray);

        $fromZeroArgs = $stringMock->allows();
        self::assertInstanceOf(HigherOrderMessage::class, $fromZeroArgs);

        $expectsMock = Mockery::mock(Recorder::class);
        $expectsString = $expectsMock->expects('record');
        self::assertInstanceOf(CompositeExpectation::class, $expectsString);
        $expectsString->andReturn('expected');
        self::assertSame('expected', $expectsMock->record('call'));

        self::assertInstanceOf(ExpectsHigherOrderMessage::class, $expectsMock->expects());
        self::assertInstanceOf(ExpectsHigherOrderMessage::class, $expectsMock->expects(null));
    }

    public function testVerificationReturnTypesMatchAnalyzerContract(): void
    {
        $spy = Mockery::spy(Recorder::class);
        $spy->record('seen', ['k' => 1]);

        $directorDirect = $spy->shouldHaveReceived('record');
        self::assertInstanceOf(VerificationDirector::class, $directorDirect);
        $directorDirect->with('seen', ['k' => 1])->once();

        $higherOrder = $spy->shouldHaveReceived();
        self::assertInstanceOf(HigherOrderMessage::class, $higherOrder);
        $directorFromHigherOrder = $higherOrder->record('seen', ['k' => 1]);
        self::assertInstanceOf(VerificationDirector::class, $directorFromHigherOrder);
        $directorFromHigherOrder->once();

        self::assertNull($spy->shouldNotHaveReceived('chain'));
        self::assertNull($spy->shouldNotHaveReceived()->chain());

        $invokable = Mockery::spy(InvokableHandler::class);
        $invokable('boot');
        $calledDirector = $invokable->shouldHaveBeenCalled();
        self::assertInstanceOf(VerificationDirector::class, $calledDirector);
        $calledDirector->once();

        $unusedInvokable = Mockery::spy(InvokableHandler::class);
        self::assertNull($unusedInvokable->shouldNotHaveBeenCalled());
    }

    public function testShouldNotHaveReceivedThrowsWhenMethodWasCalled(): void
    {
        $spy = Mockery::spy(Recorder::class);
        $spy->chain();

        $this->expectException(InvalidCountException::class);
        $spy->shouldNotHaveReceived('chain');
    }

    public function testExpectationBuilderMethodsRuntimeContracts(): void
    {
        $mock = Mockery::mock(Recorder::class);

        $using = $mock->shouldReceive('record')->with('using', Mockery::any())->andReturnUsing(
            static fn (string $name, mixed $payload): string => $name . ':' . (string) $payload,
        );
        self::assertInstanceOf(CompositeExpectation::class, $using);

        $mock->shouldReceive('record')->with('values')->andReturnValues(['first', 'second']);
        $mock->shouldReceive('chain')->andReturnSelf();
        $mock->shouldReceive('record')->with('arg', 'picked')->andReturnArg(1);
        $mock->shouldReceive('record')->withArgs(['exact', 99])->andReturn('exact-ok');
        $mock->shouldReceive('record')->withArgs(
            static fn (string $name, mixed $value = null): bool => $name === 'closure' && is_int($value) && $value > 10,
        )->andReturn('closure-ok');
        $mock->shouldReceive('record')->withSomeOfArgs('needle')->andReturn('some-ok');

        self::assertSame('using:7', $mock->record('using', 7));
        self::assertSame('first', $mock->record('values'));
        self::assertSame('second', $mock->record('values'));
        self::assertSame($mock, $mock->chain());
        self::assertSame('picked', $mock->record('arg', 'picked'));
        self::assertSame('exact-ok', $mock->record('exact', 99));
        self::assertSame('closure-ok', $mock->record('closure', 42));
        self::assertSame('some-ok', $mock->record('other', 'needle'));
    }

    public function testAndReturnArgRejectsNegativeIndex(): void
    {
        $mock = Mockery::mock(Recorder::class);

        $this->expectException(InvalidArgumentException::class);
        $mock->shouldReceive('record')->andReturnArg(-1);
    }

    public function testAllowMockingNonExistentMethodsOptInContract(): void
    {
        $defaultMock = Mockery::mock(Recorder::class);
        $defaultMock->shouldReceive('dynamicMethod')->once()->andReturn('ok');
        self::assertSame('ok', $defaultMock->dynamicMethod());

        $config = Mockery::getConfiguration();
        $previous = $config->mockingNonExistentMethodsAllowed();
        $config->allowMockingNonExistentMethods(false);
        try {
            $strictMock = Mockery::mock(Recorder::class);
            $this->expectException(Mockery\Exception::class);
            $strictMock->shouldReceive('doesNotExist');
        } finally {
            $config->allowMockingNonExistentMethods($previous);
        }
    }

    public function testArgumentMatchersRuntimeContracts(): void
    {
        $mock = Mockery::mock(Recorder::class);

        $onMatcher = Mockery::on(static fn (string $v): bool => str_starts_with($v, 'ok-'));
        self::assertInstanceOf(ClosureMatcher::class, $onMatcher);

        $captured = null;
        $captureMatcher = Mockery::capture($captured);
        self::assertInstanceOf(ClosureMatcher::class, $captureMatcher);

        $subsetMatcher = Mockery::subset(['role' => 'admin']);
        self::assertInstanceOf(Subset::class, $subsetMatcher);

        $typeMatcher = Mockery::type('string');
        self::assertInstanceOf(TypeMatcher::class, $typeMatcher);

        $anyMatcher = Mockery::any();
        self::assertInstanceOf(Any::class, $anyMatcher);

        $mustBeMatcher = Mockery::mustBe('exact');
        self::assertInstanceOf(MustBe::class, $mustBeMatcher);

        $containsMatcher = Mockery::contains('x', 'y');
        self::assertInstanceOf(Contains::class, $containsMatcher);

        $hasKeyMatcher = Mockery::hasKey('id');
        self::assertInstanceOf(HasKey::class, $hasKeyMatcher);

        $hasValueMatcher = Mockery::hasValue(42);
        self::assertInstanceOf(HasValue::class, $hasValueMatcher);

        $mock->shouldReceive('record')->with($onMatcher, $subsetMatcher)->once()->andReturn('on-subset');
        $mock->shouldReceive('record')->with('cap', $captureMatcher)->once()->andReturn('captured');
        $mock->shouldReceive('record')->with($mustBeMatcher, $containsMatcher)->once()->andReturn('must-contains');
        $mock->shouldReceive('record')->with($typeMatcher, $hasKeyMatcher)->once()->andReturn('type-key');
        $mock->shouldReceive('record')->with($anyMatcher, $hasValueMatcher)->once()->andReturn('any-val');

        self::assertSame('on-subset', $mock->record('ok-1', ['role' => 'admin', 'extra' => true]));
        self::assertSame('captured', $mock->record('cap', ['saved' => 123]));
        self::assertSame(['saved' => 123], $captured);
        self::assertSame('must-contains', $mock->record('exact', ['y', 'z', 'x']));
        self::assertSame('type-key', $mock->record('label', ['id' => 9]));
        self::assertSame('any-val', $mock->record('anything', ['answer' => 42]));
    }
}
