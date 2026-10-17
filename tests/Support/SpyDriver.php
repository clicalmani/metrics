<?php

namespace Clicalmani\Metrics\Tests\Support;

use Clicalmani\Metrics\Contracts\MetricsDriver;

final class SpyDriver implements MetricsDriver
{
    /** @var list<array{string, string, int|float, array<string, mixed>}> */
    public array $calls = [];

    public int $flushes = 0;

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->calls[] = ['increment', $key, $value, $tags];
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->calls[] = ['decrement', $key, $value, $tags];
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->calls[] = ['gauge', $key, $value, $tags];
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->calls[] = ['timing', $key, $milliseconds, $tags];
    }

    public function all(): array
    {
        return ['calls' => $this->calls];
    }

    public function flush(): void
    {
        $this->flushes++;
    }
}
