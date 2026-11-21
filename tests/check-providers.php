<?php

declare(strict_types=1);

use AlfredBez\MagoMockery\Analyzer\Providers\MockTypeFactory;
use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationKind;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\Analyzer\Protocol;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
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
    fwrite(STDOUT, "Provider type checks passed.\n");
})();
