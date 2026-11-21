<?php

declare(strict_types=1);

namespace MockeryBench;

use AlfredBez\MagoMockery\Analyzer\Providers\CompositeExpectationProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\HigherOrderMessageProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use AlfredBez\MagoMockery\Analyzer\Providers\NoArgumentFactoryProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\VerificationProvider;
use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationKind;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Metadata\TypeMetadata;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\FunctionLikeKind;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\Analyzer\TypeComparator;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\Analyzer\Protocol;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * SDK 1.60.2 internals seed the metadata that a long-lived worker has already fetched.
 * A missing entry fails through the unconnected host rather than simulating an RPC.
 *
 * @return array<string, \Closure(): mixed>
 */
function providerWorkloads(): array
{
    $cache = new MetadataCache(1);
    foreach ([Repository::class, RepositoryImpl::class, Flushable::class] as $class) {
        $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE][strtolower($class)] = true;
    }
    $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE]['unknown'] = false;
    $cache->existence[Protocol::EXISTS_TRAIT]['unknown'] = false;

    $span = new Span(0, 1);
    $location = new SourceLocation('bench/fixtures/Support.php', $span);
    foreach ([
        'with' => [['args', Type::mixed(), MetadataFlags::VARIADIC]],
        'times' => [['limit', Type::int(), 0]],
        'once' => [],
        'andSet' => [['name', Type::string(), 0], ['values', Type::array(Type::int(), Type::mixed()), MetadataFlags::VARIADIC]],
    ] as $method => $parameters) {
        $metadata = [];
        foreach ($parameters as [$name, $type, $flags]) {
            $metadata[] = new ParameterMetadata(
                $name, $location, $location, null,
                new TypeMetadata($location, $type, false, false),
                null, null, null, [], new MetadataFlags($flags),
            );
        }
        $cache->values[Protocol::GET_METHODS << 4]['mockery\\expectation' . "\0" . strtolower($method)] = new FunctionLikeMetadata(
            identifier: new FunctionLikeIdentifier(FunctionLikeKind::Method, $method, CompositeExpectationProvider::EXPECTATION),
            kind: \Mago\Sdk\Analyzer\Metadata\FunctionLikeKind::Method,
            name: $method,
            originalName: $method,
            location: $location,
            nameLocation: $location,
            parameters: $metadata,
            declaredReturnType: null,
            returnType: null,
            templates: [],
            attributes: [],
            thrownTypes: [],
            assertions: [],
            ifTrueAssertions: [],
            ifFalseAssertions: [],
            assertionsInferred: false,
            globalsAccessed: [],
            hasDocblock: true,
            flags: new MetadataFlags(0),
            availableVersions: [],
            visibility: Visibility::Public,
            final: false,
            abstract: false,
            static: false,
            constructor: false,
            whereConstraints: [],
        );
    }
    $cache->values[Protocol::GET_METHODS << 4]["mockery\\expectation\0missing"] = null;

    $host = new HostClient(new FrameCodec(), new ResourceWriter(fopen('php://memory', 'w+')));
    $cancellation = new SignalCancellationToken();
    $codebase = new Codebase($host, 1, $cancellation, $cache);
    $types = new TypeComparator($host, 1, $cancellation, $cache);
    $php = PHPVersion::fromParts(8, 4);
    $invocation = static function (string $name, string $class, Type $receiver, array $args = []) use ($span): Invocation {
        return new Invocation(InvocationKind::InstanceMethod, $name, $class, $receiver, $span, $args);
    };
    $argument = static fn (Type $type): Argument => new Argument(null, false, false, $span, 'fixture', $type);
    $context = static fn (Invocation $call): ReturnTypeProviderContext => new ReturnTypeProviderContext($php, $codebase, $call, $types, $cancellation);

    $mock = MockTypeFactory::fromInvocation(
        $invocation('mock', 'Mockery', Type::namedObject('Mockery'), [$argument(Type::literalString(Repository::class))]),
        $codebase,
    );
    if ($mock === null) {
        throw new \RuntimeException('The mock factory did not resolve the benchmark repository.');
    }
    $compositeType = CompositeExpectationProvider::typeWithMock($mock);
    $composite = new CompositeExpectationProvider();
    $higher = new HigherOrderMessageProvider();
    $noArgument = new NoArgumentFactoryProvider();
    $verification = new VerificationProvider();
    $workloads = [];

    foreach ([
        'class' => [Type::literalString(Repository::class)],
        'multiple' => [Type::literalString(Repository::class . ', ' . Flushable::class)],
        'partial' => [Type::namedObject(RepositoryImpl::class)],
        'unknown' => [Type::literalString('Unknown')],
    ] as $name => $args) {
        $call = $invocation('mock', 'Mockery', Type::namedObject('Mockery'), array_map($argument, $args));
        $workloads['factory_' . $name] = static fn () => MockTypeFactory::fromInvocation($call, $codebase);
    }
    foreach (['with', 'times', 'once', 'andSet', 'missing', 'add'] as $method) {
        $call = $invocation($method, CompositeExpectationProvider::COMPOSITE, $compositeType);
        $signatureContext = new CallableSignatureProviderContext($php, $codebase, $call, $types, $cancellation);
        $returnContext = $context($call);
        $workloads['signature_' . strtolower($method)] = static fn () => $composite->getCallableSignature($signatureContext);
        $workloads['composite_' . strtolower($method)] = static fn () => $composite->getReturnType($returnContext);
    }
    $workloads['composite_wrap'] = static fn () => CompositeExpectationProvider::typeWithMock($mock);
    $workloads['composite_extract'] = static fn () => CompositeExpectationProvider::extractMockType($compositeType);
    $foreign = new CallableSignatureProviderContext($php, $codebase, $invocation('find', Repository::class, $mock), $types, $cancellation);
    $workloads['signature_foreign'] = static fn () => $composite->getCallableSignature($foreign);
    $higherContext = $context($invocation('find', 'Mockery\\HigherOrderMessage', $noArgument->getReturnType(
        $context($invocation('shouldReceive', 'Mockery\\MockInterface', $mock)),
    )));
    $workloads['higher_order'] = static fn () => $higher->getReturnType($higherContext);
    $empty = $context($invocation('shouldReceive', 'Mockery\\MockInterface', $mock));
    $workloads['no_argument'] = static fn () => $noArgument->getReturnType($empty);
    $verify = $context($invocation('shouldHaveReceived', 'Mockery\\MockInterface', $mock, [$argument(Type::literalString('find'))]));
    $workloads['verification'] = static fn () => $verification->getReturnType($verify);

    return $workloads;
}
