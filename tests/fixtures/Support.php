<?php

declare(strict_types=1);

namespace MockeryFixture;

use Mockery\MockInterface;

interface Greeter
{
    public function greet(string $name): string;

    public function countGreetings(): int;
}

final class ApiClient
{
    public function fetch(int $id): string
    {
        return (string) $id;
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
