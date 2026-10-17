<?php

namespace Clicalmani\Metrics\Tests\Support;

use Clicalmani\Metrics\Contracts\FlushableDriver;

final class FlushableSpyDriver implements FlushableDriver
{
    /** @var list<string> */
    public array $buffer = [];

    public int $flushes = 0;

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->buffer[] = "increment {$key} {$value}";
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->buffer[] = "decrement {$key} {$value}";
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->buffer[] = "gauge {$key} {$value}";
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->buffer[] = "timing {$key} {$milliseconds}";
    }

    public function all(): array
    {
        return $this->buffer;
    }

    public function flush(): void
    {
        $this->buffer = [];
        $this->flushes++;
    }

    public function pending(): int
    {
        return count($this->buffer);
    }
}
