<?php

declare(strict_types=1);

use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use AlfredBez\MagoMockery\Analyzer\Providers\CompositeExpectationProvider;
use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationKind;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Metadata\TypeMetadata;
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

(static function (): void {
    $host = new HostClient(new FrameCodec(), new ResourceWriter(fopen('php://memory', 'w+')));
    $cancellation = new SignalCancellationToken();
    $span = new Span(0, 1);
    $cache = new MetadataCache(1);
    $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE] = ['alpha' => true, 'beta' => true];
    $codebase = new Codebase($host, 1, $cancellation, $cache);
    $factory = static function (Codebase $codebase, string ...$names) use ($span): ?Type {
        $arguments = array_map(
            static fn (string $name): Argument => new Argument(null, false, false, $span, $name, Type::literalString($name)),
            $names,
        );
        return MockTypeFactory::fromInvocation(
            new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, $arguments),
            $codebase,
        );
    };
    $check = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $targets = static function (?Type $type): array {
        if ($type === null) {
            return [];
        }
        $primary = $type->atomicTypes[0];
        return [$primary->name, ...array_map(static fn ($atomic): string => $atomic->name, $primary->intersections ?? [])];
    };

    $first = $factory($codebase, 'Alpha', 'Beta');
    $check($targets($first) === ['Alpha', 'Beta', 'Mockery\\MockInterface'], 'Factory must retain intersection order.');
    $check(serialize($factory($codebase, 'Alpha', 'Beta')) === serialize($first), 'Repeated factories must return equivalent semantic types.');
    $check($targets($factory($codebase, 'Beta', 'Alpha')) === ['Beta', 'Alpha', 'Mockery\\MockInterface'], 'Reversed targets must retain the selected primary type.');
    $check($targets($factory($codebase, 'ALPHA', 'beta')) === ['ALPHA', 'beta', 'Mockery\\MockInterface'], 'Original target casing must survive repeated factories.');
    $check($targets($factory($codebase, 'Alpha', 'ALPHA')) === ['Alpha', 'Mockery\\MockInterface'], 'Duplicate targets must remain case-insensitive.');

    $otherCache = new MetadataCache(2);
    $otherCache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE] = ['alpha' => false, 'beta' => true];
    $otherCodebase = new Codebase($host, 2, $cancellation, $otherCache);
    $check($factory($otherCodebase, 'Alpha') === null, 'Known types must not bypass existence checks in a different codebase.');
    $check($targets($factory($otherCodebase, 'Alpha', 'Beta')) === ['Beta', 'Mockery\\MockInterface'], 'Each factory must filter targets against its own codebase.');

    for ($index = 0; $index < 600; ++$index) {
        $name = 'Generated' . $index;
        $cache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE][strtolower($name)] = true;
        $check($targets($factory($codebase, $name)) === [$name, 'Mockery\\MockInterface'], 'Unique factory targets must not reuse a different type.');
    }
    $check(serialize($factory($codebase, 'Alpha', 'Beta')) === serialize($first), 'Factory results must survive long sessions with many distinct targets.');

    $location = new SourceLocation('tests/check-providers.php', $span);
    $method = static function (string $name, array $parameters, ?Visibility $visibility = Visibility::Public) use ($location): FunctionLikeMetadata {
        return new FunctionLikeMetadata(
            identifier: new FunctionLikeIdentifier(FunctionLikeKind::Method, $name, CompositeExpectationProvider::EXPECTATION),
            kind: \Mago\Sdk\Analyzer\Metadata\FunctionLikeKind::Method,
            name: $name,
            originalName: $name,
            location: $location,
            nameLocation: $location,
            parameters: $parameters,
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
            visibility: $visibility,
            final: false,
            abstract: false,
            static: false,
            constructor: false,
            whereConstraints: [],
        );
    };
    $parameter = static fn (string $name, Type $type, int $flags = 0): ParameterMetadata => new ParameterMetadata(
        $name, $location, $location, new TypeMetadata($location, $type, false, false),
        null, null, null, null, [], new MetadataFlags($flags),
    );
    $provider = new CompositeExpectationProvider();
    $signature = static function (Codebase $codebase, string $methodName) use ($provider, $span, $host, $cancellation) {
        return $provider->getCallableSignature(new CallableSignatureProviderContext(
            PHPVersion::fromParts(8, 4),
            $codebase,
            new Invocation(InvocationKind::InstanceMethod, $methodName, CompositeExpectationProvider::COMPOSITE, Type::namedObject(CompositeExpectationProvider::COMPOSITE), $span, []),
            new TypeComparator($host, 1, $cancellation),
            $cancellation,
        ));
    };
    $bucket = Protocol::GET_METHODS << 4;
    $cache->values[$bucket]["mockery\\expectation\0times"] = $method('times', [$parameter('limit', Type::int(), MetadataFlags::HAS_DEFAULT | MetadataFlags::BY_REFERENCE)]);
    $intSignature = $signature($codebase, 'times');
    $check((string) $intSignature->parameters[0]->type === 'int', 'Forwarded parameters must keep declared types.');
    $check($intSignature->parameters[0]->name === '$limit', 'Forwarded parameter names must be normalized.');
    $check($intSignature->parameters[0]->byReference && $intSignature->parameters[0]->hasDefault, 'Forwarded parameters must retain reference and default flags.');
    $check(serialize($signature($codebase, 'times')) === serialize($intSignature), 'Repeated forwarded signatures must be equivalent.');
    $check($signature($codebase, 'TIMES')->displayName === 'Mockery\\CompositeExpectation::TIMES', 'Cached parameters must preserve invocation casing in diagnostics.');

    $otherCache->values[$bucket]["mockery\\expectation\0times"] = $method('times', [$parameter('limit', Type::string())]);
    $check((string) $signature($otherCodebase, 'times')->parameters[0]->type === 'string', 'New codebase metadata must replace cached parameter types.');
    $otherCache->values[$bucket]["mockery\\expectation\0times"] = null;
    $check($signature($otherCodebase, 'times') === null, 'A previously known method must remain unresolved when absent in new metadata.');
    $otherCache->values[$bucket]["mockery\\expectation\0times"] = $method('times', [], Visibility::Private);
    $check($signature($otherCodebase, 'times') === null, 'A previously public method must remain unresolved when private.');
    $cache->values[$bucket]["mockery\\expectation\0once"] = $method('once', []);
    $check($signature($codebase, 'once')->parameters === [] && $signature($codebase, 'once')->parameters === [], 'Zero-parameter signatures must remain valid after reuse.');

    $cache->values[$bucket]["mockery\\expectation\0andset"] = $method('andSet', [
        $parameter('$name', Type::string()),
        $parameter('values', Type::array(Type::int(), Type::mixed()), MetadataFlags::VARIADIC),
    ]);
    foreach (['andSet', 'ANDSET', 'andSet'] as $name) {
        $andSet = $signature($codebase, $name);
        $check((string) $andSet->parameters[0]->type === 'string', 'andSet must retain the non-variadic parameter type.');
        $check((string) $andSet->parameters[1]->type === 'mixed' && $andSet->parameters[1]->variadic, 'andSet must accept mixed variadic values after caching.');
        $check($andSet->parameters[0]->name === '$name', 'An existing parameter dollar prefix must remain unchanged.');
    }
    fwrite(STDOUT, "Provider type and signature checks passed.\n");
})();
