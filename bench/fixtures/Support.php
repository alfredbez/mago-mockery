<?php

declare(strict_types=1);

namespace MockeryBench;

interface Repository
{
    public function find(int $id): string;

    public function count(): int;
}

interface Flushable
{
    public function flush(): void;
}

class RepositoryImpl implements Repository
{
    public function find(int $id): string
    {
        return (string) $id;
    }

    public function count(): int
    {
        return 0;
    }
}
