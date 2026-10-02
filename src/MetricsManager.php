<?php

namespace Clicalmani\Metrics;

use Clicalmani\Metrics\Contracts\MetricsDriver;

final class MetricsManager
{
    private MetricsDriver $driver;

    /** @var array<string, mixed> Tags appliqués à toutes les métriques. */
    private array $defaultTags = [];

    public function __construct(?MetricsDriver $driver = null)
    {
        $this->driver = $driver ?? new \Clicalmani\Metrics\Drivers\InMemoryDriver();
    }

    // ─────────────────────────────────────────────────────────────
    // API principale
    // ─────────────────────────────────────────────────────────────

    public function increment(string $key, int $value = 1, array $tags = []): self
    {
        $this->driver->increment($key, $value, $this->mergeTags($tags));

        return $this;
    }

    public function decrement(string $key, int $value = 1, array $tags = []): self
    {
        $this->driver->decrement($key, $value, $this->mergeTags($tags));

        return $this;
    }

    public function gauge(string $key, float|int $value, array $tags = []): self
    {
        $this->driver->gauge($key, $value, $this->mergeTags($tags));

        return $this;
    }

    public function timing(string $key, float $milliseconds, array $tags = []): self
    {
        $this->driver->timing($key, $milliseconds, $this->mergeTags($tags));

        return $this;
    }

    /**
     * Mesure automatiquement la durée d'un callback.
     */
    public function measure(string $key, callable $callback, array $tags = []): mixed
    {
        $start = microtime(true);

        try {
            return $callback();
        } finally {
            $this->timing($key, (microtime(true) - $start) * 1000, $tags);
        }
    }

    /**
     * Applique une série de tags pour tous les appels suivants
     * dans le scope d'un callback (pattern "withTags").
     */
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
    // Gestion du driver
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

    /**
     * Raccourci : remplace le driver par une instance du nom de classe fourni.
     */
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

    public function flush(): self
    {
        $this->driver->flush();

        return $this;
    }

    public function defaultTags(array $tags): self
    {
        $this->defaultTags = $tags;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────

    private function mergeTags(array $tags): array
    {
        return array_merge($this->defaultTags, $tags);
    }
}