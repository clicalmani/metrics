<?php

namespace Clicalmani\Metrics;

use Clicalmani\Metrics\Contracts\MetricsDriver;
use Clicalmani\Metrics\Contracts\FlushableDriver;
use Clicalmani\Metrics\Sampling\{
    Sampler,
    PatternSampler,
    AlwaysSampler,
};

final class MetricsManager
{
    private MetricsDriver $driver;

    /** @var array<string, mixed> */
    private array $defaultTags = [];

    private Sampler $sampler;

    private CardinalityGuard $cardinality;

    private int $flushInterval = 0;      // secondes, 0 = désactivé
    private ?int $lastFlushAt = null;

    public function __construct(?MetricsDriver $driver = null)
    {
        $this->driver      = $driver ?? new \Clicalmani\Metrics\Drivers\InMemoryDriver();
        $this->sampler     = new AlwaysSampler();
        $this->cardinality = new CardinalityGuard(limit: 0);
    }

    public function __init__(array $tags, array $sampling = [], int $cardinalityLimit = 0, int $flushInterval = 0) : void
    {
        // ─────────────────────────────────────────────────────
        // Tags globaux
        // ─────────────────────────────────────────────────────
        $this->defaultTags($tags);

        // ─────────────────────────────────────────────────────
        // Sampling
        // ─────────────────────────────────────────────────────
        if ($sampling) {
            $this->sampling($sampling);
        }

        // ─────────────────────────────────────────────────────
        // Cardinality
        // ─────────────────────────────────────────────────────
        if ($cardinalityLimit > 0) {
            $this->cardinalityLimit(
                limit:  $cardinalityLimit,
                onDrop: function (string $key, array $tags) {
                    logger()->warning(
                        "Metrics cardinality limit exceeded for {$key}",
                        ['tags' => $tags]
                    );
                }
            );
        }

        // ─────────────────────────────────────────────────────
        // Flush interval
        // ─────────────────────────────────────────────────────
        if ($flushInterval > 0) {
            $this->flushInterval($flushInterval);
        }
    }

    // ─────────────────────────────────────────────────────────────
    // API principale
    // ─────────────────────────────────────────────────────────────

    public function increment(string $key, int $value = 1, array $tags = []): self
    {
        if ($this->shouldEmit($key, $tags)) {
            $this->driver->increment($key, $value, $this->mergeTags($tags));
        }

        $this->maybeFlush();

        return $this;
    }

    public function decrement(string $key, int $value = 1, array $tags = []): self
    {
        if ($this->shouldEmit($key, $tags)) {
            $this->driver->decrement($key, $value, $this->mergeTags($tags));
        }

        $this->maybeFlush();

        return $this;
    }

    public function gauge(string $key, float|int $value, array $tags = []): self
    {
        if ($this->shouldEmit($key, $tags)) {
            $this->driver->gauge($key, $value, $this->mergeTags($tags));
        }

        $this->maybeFlush();

        return $this;
    }

    public function timing(string $key, float $milliseconds, array $tags = []): self
    {
        if ($this->shouldEmit($key, $tags)) {
            $this->driver->timing($key, $milliseconds, $this->mergeTags($tags));
        }

        $this->maybeFlush();

        return $this;
    }

    public function measure(string $key, callable $callback, array $tags = []): mixed
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $this->timing($key, (microtime(true) - $start) * 1000, $tags);
        }
    }

    public function withTags(array $tags, callable $callback): mixed
    {
        $previous = $this->defaultTags;
        $this->defaultTags = array_merge($previous, $tags);

        try {
            return $callback($this);
        } finally {
            $this->defaultTags = $previous;
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Sampling
    // ─────────────────────────────────────────────────────────────

    public function sampling(array $rules): self
    {
        $this->sampler = new PatternSampler($rules);

        return $this;
    }

    public function sampler(Sampler $sampler): self
    {
        $this->sampler = $sampler;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // Cardinality
    // ─────────────────────────────────────────────────────────────

    public function cardinalityLimit(int $limit, ?\Closure $onDrop = null): self
    {
        $this->cardinality = new CardinalityGuard($limit, $onDrop);

        return $this;
    }

    public function cardinality(): CardinalityGuard
    {
        return $this->cardinality;
    }

    // ─────────────────────────────────────────────────────────────
    // Flush
    // ─────────────────────────────────────────────────────────────

    public function flushInterval(int $seconds): self
    {
        $this->flushInterval = $seconds;
        $this->lastFlushAt   = time();

        return $this;
    }

    public function flush(): self
    {
        $this->driver->flush();
        $this->lastFlushAt = time();

        return $this;
    }

    public function pending(): int
    {
        return $this->driver instanceof FlushableDriver
            ? $this->driver->pending()
            : 0;
    }

    // ─────────────────────────────────────────────────────────────
    // Driver
    // ─────────────────────────────────────────────────────────────

    public function driver(): MetricsDriver
    {
        return $this->driver;
    }

    public function setDriver(MetricsDriver $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function use(string $driverClass, mixed ...$args): self
    {
        $this->driver = new $driverClass(...$args);

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // Introspection
    // ─────────────────────────────────────────────────────────────

    public function all(): array
    {
        return $this->driver->all();
    }

    public function defaultTags(array $tags): self
    {
        $this->defaultTags = $tags;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────
    // Internes
    // ─────────────────────────────────────────────────────────────

    private function shouldEmit(string $key, array $tags): bool
    {
        if (! $this->sampler->shouldSample($key)) {
            return false;
        }

        return $this->cardinality->allow($key, $tags);
    }

    private function maybeFlush(): void
    {
        if ($this->flushInterval <= 0) {
            return;
        }

        $now = time();

        if ($this->lastFlushAt === null) {
            $this->lastFlushAt = $now;
            return;
        }

        if (($now - $this->lastFlushAt) >= $this->flushInterval) {
            $this->flush();
        }
    }

    private function mergeTags(array $tags): array
    {
        return array_merge($this->defaultTags, $tags);
    }
}