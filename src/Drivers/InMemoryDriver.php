<?php

namespace Clicalmani\Metrics\Drivers;

use Clicalmani\Metrics\Contracts\MetricsDriver;

final class InMemoryDriver implements MetricsDriver
{
    /** @var array<string, int|float> */
    private array $counters = [];

    /** @var array<string, float|int> */
    private array $gauges = [];

    /** @var array<string, list<float>> */
    private array $timings = [];

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->counters[$this->key($key, $tags)] =
            ($this->counters[$this->key($key, $tags)] ?? 0) + $value;
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->increment($key, -$value, $tags);
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->gauges[$this->key($key, $tags)] = $value;
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->timings[$this->key($key, $tags)][] = $milliseconds;
    }

    public function all(): array
    {
        return [
            'counters' => $this->counters,
            'gauges'   => $this->gauges,
            'timings'  => $this->timings,
        ];
    }

    public function flush(): void
    {
        // Rien à faire — tout est déjà en mémoire.
    }

    private function key(string $key, array $tags): string
    {
        if (empty($tags)) {
            return $key;
        }

        ksort($tags);

        return $key . '[' . http_build_query($tags, '', ',') . ']';
    }
}