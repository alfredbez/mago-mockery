<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery\MockInterface;

interface Greeter
{
    public function greet(string $name): string;

    public function countGreetings(): int;
}

final class GreeterImpl implements Greeter
{
    public function greet(string $name): string
    {
        return $name;
    }

    public function countGreetings(): int
    {
        return 0;
    }
}

final class ApiClient
{
    public function fetch(int $id): string
    {
        return (string) $id;
    }
}

trait LoggableTrait
{
    public const TAG = 'trait-tag';

    public function logTraitMessage(string $message): string
    {
        return $message;
    }
}

/**
 * @template T
 */
final class GenericBox
{
    /** @param T $value */
    public function __construct(public mixed $value)
    {
    }

    /** @return T */
    public function get(): mixed
    {
        return $this->value;
    }

    /** @param T $next */
    public function put(mixed $next): void
    {
        $this->value = $next;
    }
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final class Before
{
}

trait CrossFileNamedMockTrait
{
    #[Before]
    public function registerCrossFileAlias(): void
    {
        \Mockery::namedMock('CrossFileTraitBeforeAlias', Greeter::class);
    }
}

class Support
{
    public function acceptGreeter(Greeter&MockInterface $greeter): void
    {
        $greeter->greet('inner');
    }

    public function acceptApiClient(ApiClient $client): void
    {
        $client->fetch(1);
    }
}
