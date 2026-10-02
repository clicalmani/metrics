<?php

namespace Clicalmani\Metrics\Drivers;

use Clicalmani\Metrics\Contracts\MetricsDriver;

final class LogDriver implements MetricsDriver
{
    public function __construct(private $logger) {}

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->logger->info("metric.counter.{$key}", ['value' => $value] + $tags);
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->logger->info("metric.counter.{$key}", ['value' => -$value] + $tags);
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->logger->info("metric.gauge.{$key}", ['value' => $value] + $tags);
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->logger->info("metric.timing.{$key}", ['ms' => $milliseconds] + $tags);
    }

    public function all(): array { return []; }
    public function flush(): void {}
}