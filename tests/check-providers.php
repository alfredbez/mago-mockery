<?php

declare(strict_types=1);

use AlfredBez\MagoMockery\Analyzer\Providers\HigherOrderMessageProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use AlfredBez\MagoMockery\Analyzer\Providers\CompositeExpectationProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\NoArgumentFactoryProvider;
use AlfredBez\MagoMockery\Analyzer\Providers\TraitMockConstantIssueFilter;
use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationKind;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Metadata\TypeMetadata;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\ClassLikeStringKind;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\FunctionLikeIdentifier;
use Mago\Sdk\Analyzer\Type\FunctionLikeKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\Analyzer\TypeComparator;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\Analyzer\Protocol;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Annotation;
use Mago\Sdk\Reporting\AnnotationKind;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\ReportedIssue;
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
    $check($targets($factory($codebase, 'Alpha [foo, bar]|Beta')) === ['Alpha', 'Beta', 'Mockery\\MockInterface'], 'Pipe-separated partial specs with whitespace must resolve all targets.');
    $check($targets($factory($codebase, 'alias:Alpha|Beta')) === ['Alpha', 'Beta', 'Mockery\\MockInterface'], 'Pipe-separated alias specs must resolve all targets.');

    $otherCache = new MetadataCache(2);
    $otherCache->existence[Protocol::EXISTS_CLASS_OR_INTERFACE] = ['alpha' => false, 'beta' => true, 'gammatrait' => false];
    $otherCache->existence[Protocol::EXISTS_TRAIT] = ['alpha' => false, 'gammatrait' => true];
    $otherCodebase = new Codebase($host, 2, $cancellation, $otherCache);
    $check($factory($otherCodebase, 'Alpha') === null, 'Known types must not bypass existence checks in a different codebase.');
    $check($targets($factory($otherCodebase, 'Alpha', 'Beta')) === ['Beta', 'Mockery\\MockInterface'], 'Each factory must filter targets against its own codebase.');
    $check($targets($factory($otherCodebase, 'GammaTrait')) === ['GammaTrait', 'Mockery\\MockInterface'], 'Trait targets must resolve to Trait&MockInterface.');

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

    $cache->values[$bucket]["mockery\\expectation\0set"] = $method('set', [
        $parameter('$name', Type::string()),
        $parameter('value', Type::mixed()),
    ]);
    $setSignature = $signature($codebase, 'set');
    $check((string) $setSignature->parameters[0]->type === 'string', 'set must retain the property name type.');
    $check((string) $setSignature->parameters[1]->type === 'mixed' && $setSignature->parameters[1]->variadic, 'set must accept mixed variadic values like andSet.');

    $typedClassString = Type::fromAtomic(new ScalarType(
        ScalarTypeKind::ClassLikeString,
        new ClassLikeStringType(
            variant: ClassLikeStringVariant::OfType,
            kind: ClassLikeStringKind::Class_,
            constraint: new NamedObjectType('Alpha', null, null, false, false, null, false),
        ),
    ));
    $bareClassString = Type::fromAtomic(new ScalarType(
        ScalarTypeKind::ClassLikeString,
        new ClassLikeStringType(variant: ClassLikeStringVariant::Any, kind: ClassLikeStringKind::Class_),
    ));
    $fromTypedClassString = MockTypeFactory::fromInvocation(
        new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, [
            new Argument(null, false, false, $span, '$cls', $typedClassString),
        ]),
        $codebase,
    );
    $check($targets($fromTypedClassString) === ['Alpha', 'Mockery\\MockInterface'], 'class-string<Alpha> must resolve to Alpha&MockInterface.');
    $fromBareClassString = MockTypeFactory::fromInvocation(
        new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, [
            new Argument(null, false, false, $span, '$cls', $bareClassString),
        ]),
        $codebase,
    );
    $check($fromBareClassString === null, 'Bare class-string must stay unresolved.');

    $genericClassString = Type::fromAtomic(new ScalarType(
        ScalarTypeKind::ClassLikeString,
        new ClassLikeStringType(
            variant: ClassLikeStringVariant::OfType,
            kind: ClassLikeStringKind::Class_,
            constraint: new NamedObjectType('Alpha', [Type::string()], null, false, false, null, false),
        ),
    ));
    $fromGenericClassString = MockTypeFactory::fromInvocation(
        new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, [
            new Argument(null, false, false, $span, '$cls', $genericClassString),
        ]),
        $codebase,
    );
    $genericPrimary = $fromGenericClassString?->atomicTypes[0] ?? null;
    $check(
        $genericPrimary instanceof NamedObjectType
        && $genericPrimary->name === 'Alpha'
        && $genericPrimary->parameters !== null
        && isset($genericPrimary->parameters[0])
        && (string) $genericPrimary->parameters[0] === 'string',
        'class-string<Alpha<string>> must keep the generic parameter on Alpha&MockInterface.',
    );

    $idShape = Type::fromAtomic(new KeyedArrayType(
        knownItems: [new ArrayItem(new ArrayKey(ArrayKeyKind::String, 'id'), false, Type::int())],
        keyType: null,
        valueType: null,
        nonEmpty: true,
    ));
    $nameShape = Type::fromAtomic(new KeyedArrayType(
        knownItems: [new ArrayItem(new ArrayKey(ArrayKeyKind::String, 'name'), false, Type::string())],
        keyType: null,
        valueType: null,
        nonEmpty: true,
    ));
    $idBoxMock = MockTypeFactory::fromInvocation(
        new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, [
            new Argument(null, false, false, $span, '$box', Type::fromAtomic(new NamedObjectType('Alpha', [$idShape], null, false, false, null, false))),
        ]),
        $codebase,
    );
    $nameBoxMock = MockTypeFactory::fromInvocation(
        new Invocation(InvocationKind::StaticMethod, 'mock', 'Mockery', Type::namedObject('Mockery'), $span, [
            new Argument(null, false, false, $span, '$box', Type::fromAtomic(new NamedObjectType('Alpha', [$nameShape], null, false, false, null, false))),
        ]),
        $codebase,
    );
    $idBoxPrimary = $idBoxMock?->atomicTypes[0] ?? null;
    $nameBoxPrimary = $nameBoxMock?->atomicTypes[0] ?? null;
    $check(
        $idBoxPrimary instanceof NamedObjectType
        && $nameBoxPrimary instanceof NamedObjectType
        && $idBoxPrimary->parameters !== null
        && $nameBoxPrimary->parameters !== null
        && serialize($idBoxPrimary->parameters[0]) === serialize($idShape)
        && serialize($nameBoxPrimary->parameters[0]) === serialize($nameShape),
        'Distinct generic array-shape mocks must not collide in MockTypeFactory cache.',
    );

    $cache->values[Protocol::GET_CLASS_LIKES << 4]['alpha'] = new ClassLikeMetadata(
        name: 'Alpha',
        originalName: 'Alpha',
        kind: ClassLikeKind::Interface,
        location: $location,
        nameLocation: $location,
        flags: new MetadataFlags(0),
        directParentClass: null,
        directParentInterfaces: [],
        parentInterfaces: [],
        parentClasses: [],
        requiredExtends: [],
        requiredImplements: [],
        usedTraits: [],
        unresolvedHierarchyDependencies: [],
        methods: ['known'],
        pseudoMethods: [],
        staticPseudoMethods: [],
        properties: [],
        magicProperties: [],
        constants: [],
        enumCases: [],
        children: null,
        permittedInheritors: null,
        templates: [],
        attributes: [],
        typeAliases: [],
        mixins: [],
        enumType: null,
        sealedMethods: null,
        sealedProperties: null,
        availableVersions: [],
    );
    $cache->existence[256 | Protocol::EXISTS_METHOD]["alpha\0__call"] = false;
    $cache->existence[256 | Protocol::EXISTS_METHOD]["alpha\0__callstatic"] = false;
    $cache->existence[256 | Protocol::EXISTS_METHOD]["alpha\0known"] = true;
    $cache->existence[256 | Protocol::EXISTS_METHOD]["alpha\0missing"] = false;

    $noArg = new NoArgumentFactoryProvider();
    $hoType = $noArg->getReturnType(new ReturnTypeProviderContext(
        PHPVersion::fromParts(8, 4),
        $codebase,
        new Invocation(InvocationKind::InstanceMethod, 'shouldReceive', 'Mockery\\MockInterface', $fromTypedClassString, $span, []),
        new TypeComparator($host, 1, $cancellation),
        $cancellation,
    ));
    $higher = new HigherOrderMessageProvider();
    $hoKnown = $higher->getReturnType(new ReturnTypeProviderContext(
        PHPVersion::fromParts(8, 4),
        $codebase,
        new Invocation(InvocationKind::InstanceMethod, 'known', 'Mockery\\HigherOrderMessage', $hoType, $span, []),
        new TypeComparator($host, 1, $cancellation),
        $cancellation,
    ));
    $check($hoKnown !== null, 'Higher-order provider must resolve known methods on target class.');
    $hoMissing = $higher->getReturnType(new ReturnTypeProviderContext(
        PHPVersion::fromParts(8, 4),
        $codebase,
        new Invocation(InvocationKind::InstanceMethod, 'missing', 'Mockery\\HigherOrderMessage', $hoType, $span, []),
        new TypeComparator($host, 1, $cancellation),
        $cancellation,
    ));
    $check($hoMissing !== null, 'Higher-order provider must forward unknown methods so native diagnostics do not cascade.');
    $hoMissingSig = $higher->getCallableSignature(new CallableSignatureProviderContext(
        PHPVersion::fromParts(8, 4),
        $codebase,
        new Invocation(InvocationKind::InstanceMethod, 'missing', 'Mockery\\HigherOrderMessage', $hoType, $span, []),
        new TypeComparator($host, 1, $cancellation),
        $cancellation,
    ));
    $check($hoMissingSig !== null, 'Higher-order signature provider must forward unknown methods.');

    $traitFilter = new TraitMockConstantIssueFilter();
    $filterDecision = static function (string $code, string $needle) use ($traitFilter, $codebase, $host, $cancellation): IssueFilterDecision {
        $pos = strrpos($code, $needle);
        if ($pos === false) {
            throw new RuntimeException("Needle not found in filter check: {$needle}");
        }
        $issueSpan = new Span($pos, $pos + strlen($needle));
        $issue = new ReportedIssue(
            level: Level::Error,
            code: 'direct-trait-constant-access',
            message: 'Cannot access trait constant directly.',
            notes: [],
            help: null,
            link: null,
            annotations: [new Annotation(AnnotationKind::Primary, $issueSpan, 'trait')],
            edits: [],
        );

        return $traitFilter->filterIssue(new IssueFilterContext(
            PHPVersion::fromParts(8, 3),
            $codebase,
            new TypeComparator($host, 1, $cancellation),
            $cancellation,
            'tests/check-providers.php',
            $code,
            $issue,
        ));
    };

    $check(
        $filterDecision('<?php use Mockery; function f(bool $b) { $v = T::class; if ($b) { $v = Mockery::mock(T::class); } return $v::TAG; }', '$v::TAG') === IssueFilterDecision::Keep,
        'Conditional mock over plain trait class-string must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $b) { if ($b) { $v = Mockery::mock(T::class); } else { $v = Mockery::spy(T::class); } return $v::TAG; }', '$v::TAG') === IssueFilterDecision::Remove,
        'Dominated mock across all branches must suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $a = &$m; return $a::TAG; }', '$a::TAG') === IssueFilterDecision::Remove,
        'By-reference mock alias must suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php function f() { $p = T::class; $a = &$p; return $a::TAG; }', '$a::TAG') === IssueFilterDecision::Keep,
        'By-reference non-mock alias must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $a = &$m; $m = T::class; return $a::TAG; }', '$a::TAG') === IssueFilterDecision::Keep,
        'By-reference alias invalidated via original must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $a = Mockery::mock(T::class); $b = &$a; $a = &$b; return $b::TAG; }', '$b::TAG') === IssueFilterDecision::Remove,
        'Reference cycle on mock must terminate and suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $c) { $a = Mockery::mock(T::class); $b = Mockery::mock(T::class); if ($c) { $b = &$a; } $a = T::class; return $b::TAG; }', '$b::TAG') === IssueFilterDecision::Keep,
        'Conditional by-reference alias invalidated through original must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $c) { $ct = Mockery::getContainer(); if ($c) { $ct = new Fake(); } $m = $ct->mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Conditionally overwritten Container variable must keep diagnostic.',
    );

    $fnBucket = Protocol::GET_FUNCTIONS << 4;
    $makeFunction = static function (string $name, array $parameters, int $flags = 0) use ($location): FunctionLikeMetadata {
        return new FunctionLikeMetadata(
            identifier: new FunctionLikeIdentifier(FunctionLikeKind::Function_, $name, null),
            kind: \Mago\Sdk\Analyzer\Metadata\FunctionLikeKind::Function_,
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
            hasDocblock: false,
            flags: new MetadataFlags($flags),
            availableVersions: [],
            visibility: null,
            final: false,
            abstract: false,
            static: false,
            constructor: false,
            whereConstraints: [],
        );
    };
    $cache->values[$fnBucket]['overwrite'] = $makeFunction('overwrite', [$parameter('x', Type::mixed(), MetadataFlags::BY_REFERENCE)]);
    $cache->values[$fnBucket]['inspect'] = $makeFunction('inspect', [$parameter('x', Type::mixed())]);
    $cache->values[$fnBucket]['strlen'] = $makeFunction('strlen', [$parameter('string', Type::string())], MetadataFlags::BUILTIN | MetadataFlags::PURE);
    $cache->values[$fnBucket]['implode'] = $makeFunction('implode', [$parameter('separator', Type::string()), $parameter('array', Type::array(Type::int(), Type::mixed()))], MetadataFlags::BUILTIN | MetadataFlags::PURE);
    $cache->values[$fnBucket]['fakepure'] = $makeFunction('fakePure', [], MetadataFlags::USER_DEFINED | MetadataFlags::PURE);
    $cache->values[$fnBucket]['mock'] = $makeFunction('mock', [$parameter('args', Type::mixed(), MetadataFlags::VARIADIC)], MetadataFlags::USER_DEFINED);
    $cache->values[$fnBucket]['reenter'] = $makeFunction('reenter', [], MetadataFlags::USER_DEFINED);
    $cache->values[$bucket]["purebase\0poke"] = new FunctionLikeMetadata(
        identifier: new FunctionLikeIdentifier(FunctionLikeKind::Method, 'poke', 'PureBase'),
        kind: \Mago\Sdk\Analyzer\Metadata\FunctionLikeKind::Method,
        name: 'poke',
        originalName: 'poke',
        location: $location,
        nameLocation: $location,
        parameters: [],
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
        flags: new MetadataFlags(MetadataFlags::USER_DEFINED | MetadataFlags::PURE),
        availableVersions: [],
        visibility: Visibility::Protected,
        final: false,
        abstract: false,
        static: false,
        constructor: false,
        whereConstraints: [],
    );

    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); overwrite($m); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'By-reference function call must invalidate receiver mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); inspect($m); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'By-value function call must preserve receiver mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(callable $fn) { $m = Mockery::mock(T::class); $fn($m); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Unresolved dynamic callable must conservatively invalidate variable arguments.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($o) { $m = T::class; $o?->accept($m = Mockery::mock(T::class)); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Nullsafe call argument assignment must not produce an unconditional mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $flag) { $m = Mockery::mock(T::class); $flag ? $m = T::class : null; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Unparenthesized assignment inside ternary branch must invalidate receiver.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); return match (T::class) { ($m = T::class) => $m::TAG }; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Match-arm condition assignment must apply before arm result.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $box = []; $box[0] = &$m; $box[0] = T::class; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Reference escaped into array element must invalidate on array write.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $box = [T::class]; foreach ($box as &$v) {} $v = Mockery::mock(T::class); $box[0] = T::class; return $v::TAG; }', '$v::TAG') === IssueFilterDecision::Keep,
        'Foreach by-reference variable must remain escaped across reassignment.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $reset = function () use (&$m) { $m = T::class; }; $m = Mockery::mock(T::class); $reset(); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Closure by-reference capture must invalidate escaped cell on subsequent invocation.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $xs = []; unset($xs[$m = T::class]); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Complex unset target must evaluate index assignment side effects.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); for ($i = 0; $i < 2; ++$i) { $m::TAG; new class($m = T::class) { public function __construct($ignored) {} }; } }', '$m::TAG') === IssueFilterDecision::Keep,
        'Loop write scan must inspect anonymous-class constructor arguments.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); overwrite(($m)); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Parenthesized by-reference function argument must invalidate receiver mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $a = &$m; try { mayThrow(); } catch (\Throwable $a) { $a = T::class; } return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Catch variable sharing a reference cell must invalidate aliased mock.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($o) { $m = Mockery::mock(T::class); for ($i = 0; $i < 2; ++$i) { $m::TAG; $o->with($m); } }', '$m::TAG') === IssueFilterDecision::Keep,
        'Loop write scan must not exempt arbitrary with() calls from by-reference invalidation.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($x) { $m = Mockery::mock(T::class); isset($x) === ($m = T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Trailing comparison operand after isset() must execute assignment side effects.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(array $a) { $m = T::class; $nil = null; isset($nil, $a[($m = Mockery::mock(T::class)) ? 0 : 1]); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Short-circuited trailing isset() argument must not produce an unconditional mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; $m = Mockery::mock(T::class); $GLOBALS[\'m\'] = T::class; $x = $m::TAG;', '$m::TAG') === IssueFilterDecision::Keep,
        'File-scope $GLOBALS write must invalidate top-level mock guarantee.',
    );

    $check(
        $filterDecision('<?php use Mockery; function f() { static $m; $m = Mockery::mock(T::class); strlen("x"); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Static-local mock must survive intervening pure built-in call.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { static $m; $m = Mockery::mock(T::class); reenter(); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Static-local mock must invalidate on potentially re-entrant user call.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { static $m; $m = Mockery::mock(T::class); overwrite($m); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Static-local mock passed by reference must invalidate.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { static $m = null; $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Static-local initializer must not guarantee null on subsequent calls.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); do { $r = $m::TAG; $m = T::class; } while (false); return $r; }', '$m::TAG') === IssueFilterDecision::Remove,
        'do-while(false) first iteration must preserve pre-loop mock before later body write.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); do { $r = $m::TAG; $m = T::class; } while (0); return $r; }', '$m::TAG') === IssueFilterDecision::Remove,
        'do-while(0) first iteration must preserve pre-loop mock before later body write.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $c) { $m = Mockery::mock(T::class); do { $r = $m::TAG; $m = T::class; } while ($c); return $r; }', '$m::TAG') === IssueFilterDecision::Keep,
        'do-while with dynamic condition must keep diagnostic when body invalidates receiver.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); do { $m = T::class; $r = $m::TAG; } while (false); return $r; }', '$m::TAG') === IssueFilterDecision::Keep,
        'do-while(false) access after body reassignment must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $b) { $m = T::class; do { if ($b) { break; } $m = Mockery::mock(T::class); } while (false); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'do-while(false) early break before mock assignment must keep post-loop diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = T::class; do { $m = Mockery::mock(T::class); } while (false); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'do-while(false) unconditional body mock assignment must dominate post-loop access.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class)->shouldDeferMissing(); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'shouldDeferMissing() fluent call must preserve mock provenance.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class)->unknownMethod(); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Unknown method call on mock must not preserve mock provenance.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $c = clone Mockery::mock(T::class); return $c::TAG; }', '$c::TAG') === IssueFilterDecision::Remove,
        'Direct clone of mock must preserve mock provenance.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $p = T::class; $c = clone $p; return $c::TAG; }', '$c::TAG') === IssueFilterDecision::Keep,
        'Clone of non-mock must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = null; $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Null-coalescing assignment from known null to mock must suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $m ??= T::class; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Null-coalescing assignment on known mock must preserve mock guarantee.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = T::class; $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Null-coalescing assignment on non-mock class-string must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $b) { $m = $b ? Mockery::mock(T::class) : null; $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Null-coalescing assignment on nullable mock with mock fallback must suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f(bool $b) { $m = $b ? Mockery::mock(T::class) : null; $m ??= T::class; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Null-coalescing assignment on nullable mock with non-mock fallback must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($m) { $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Null-coalescing assignment on unknown parameter must keep diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = T::class; unset($m); $m ??= Mockery::mock(T::class); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Null-coalescing assignment after unset to mock must suppress diagnostic.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { $m = T::class; $fn = static function () use (&$m) { $m = T::class; }; $m = Mockery::mock(T::class); $_ = clone $evil; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'clone must invalidate escaped reference cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { static $m; $m = Mockery::mock(T::class); $_ = clone $evil; return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'clone must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { $v = null; $fn = static function () use (&$v) { $v = T::class; }; $v = null; $_ = clone $evil; $v ??= Mockery::mock(T::class); return $v::TAG; }', '$v::TAG') === IssueFilterDecision::Keep,
        'clone must invalidate escaped null before coalesce assignment.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { static $m; $m = Mockery::mock(T::class); for ($i = 0; $i < 2; ++$i) { $r = $m::TAG; $_ = clone $evil; } return $r; }', '$m::TAG') === IssueFilterDecision::Keep,
        'clone inside loop must invalidate static-local cells across back-edge.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $c = clone(object: $m, withProperties: ["x" => 1]); return $c::TAG; }', '$c::TAG') === IssueFilterDecision::Remove,
        'PHP 8.5 clone with named object argument must preserve mock provenance.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $c = clone(withProperties: ["x" => 1], object: $m); return $c::TAG; }', '$c::TAG') === IssueFilterDecision::Remove,
        'PHP 8.5 clone with reversed named arguments must preserve mock provenance.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $other = Mockery::mock(T::class); $c = clone(object: $m, withProperties: [$other = T::class]); return $other::TAG; }', '$other::TAG') === IssueFilterDecision::Keep,
        'PHP 8.5 clone withProperties argument must evaluate side effects.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $m = Mockery::mock(T::class); $c = clone(withProperties: [$m = T::class], object: $m); return $c::TAG; }', '$c::TAG') === IssueFilterDecision::Keep,
        'PHP 8.5 clone named arguments must evaluate left to right.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { $other = T::class; static $a = ($other = Mockery::mock(T::class)); return $other::TAG; }', '$other::TAG') === IssueFilterDecision::Keep,
        'Static-local initializer side effect must not guarantee mock on subsequent calls.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { static $m; $m = Mockery::mock(T::class); fakePure(); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'User-defined @pure function must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; class PureBase { public function f() { static $m; $m = Mockery::mock(T::class); $this->poke(); return $m::TAG; } }', '$m::TAG') === IssueFilterDecision::Keep,
        'Overridable @pure method must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { static $m; $m = Mockery::mock(T::class); implode("", [$evil]); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Pure built-in with array/object argument must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($evil) { static $m; $m = Mockery::mock(T::class); strlen($evil); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Pure built-in with variable argument must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f() { static $m; $m = Mockery::mock(T::class); strlen("a" . "b"); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Remove,
        'Pure built-in with literal concatenation argument must preserve static-local mock.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($cb) { static $m; $m = Mockery::mock(T::class); Mockery::mock(CtorInvoker::class, [$cb]); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Mockery::mock factory call must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($cb) { $ct = Mockery::getContainer(); static $m; $m = Mockery::mock(T::class); $ct->mock(CtorInvoker::class, [$cb]); return $m::TAG; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Container::mock factory call must invalidate static-local cells.',
    );
    $check(
        $filterDecision('<?php use Mockery; function f($cb) { static $m; $m = Mockery::mock(T::class); for ($i = 0; $i < 2; ++$i) { $r = $m::TAG; mock(CtorInvoker::class, [$cb]); } return $r; }', '$m::TAG') === IssueFilterDecision::Keep,
        'Global mock helper inside loop must invalidate static-local cells.',
    );

    fwrite(STDOUT, "Provider type and signature checks passed.\n");
})();
