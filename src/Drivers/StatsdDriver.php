<?php

namespace Clicalmani\Metrics\Drivers;

use Clicalmani\Metrics\Contracts\MetricsDriver;

final class StatsdDriver implements MetricsDriver
{
    private $socket;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 8125,
        private string $prefix = '',
    ) {
        $this->socket = stream_socket_client("udp://{$host}:{$port}", $errno, $errstr);
        if (! $this->socket) {
            throw new \RuntimeException("StatsD connection failed: $errstr");
        }
    }

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->write("{$key}:{$value}|c", $tags);
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->write("{$key}:-{$value}|c", $tags);
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->write("{$key}:{$value}|g", $tags);
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->write("{$key}:{$milliseconds}|ms", $tags);
    }

    public function all(): array { return []; }
    public function flush(): void {}

    private function write(string $payload, array $tags): void
    {
        $tagsStr = '';
        if (! empty($tags)) {
            $parts = [];
            foreach ($tags as $k => $v) {
                $parts[] = "{$k}:{$v}";
            }
            $tagsStr = '|#' . implode(',', $parts);
        }

        fwrite($this->socket, "{$this->prefix}{$payload}{$tagsStr}\n");
    }
}