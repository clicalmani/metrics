<?php

namespace Clicalmani\Metrics\Drivers;

use Clicalmani\Metrics\Contracts\FlushableDriver;

final class StatsdDriver implements FlushableDriver
{
    private $socket;

    /** @var string[] */
    private array $buffer = [];

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 8125,
        private string $prefix = '',
        private int $maxBufferSize = 500,
        private int $maxDatagramSize = 1432,
    ) {
        $this->socket = stream_socket_client(
            "udp://{$this->host}:{$this->port}",
            $errno,
            $errstr
        );

        if (! $this->socket) {
            throw new \RuntimeException("StatsD connection failed: $errstr");
        }

        // Non-bloquant pour éviter les ralentissements si l'agent est down
        stream_set_blocking($this->socket, false);
    }

    public function increment(string $key, int $value = 1, array $tags = []): void
    {
        $this->buffer("{$this->prefix}{$key}:{$value}|c", $tags);
    }

    public function decrement(string $key, int $value = 1, array $tags = []): void
    {
        $this->buffer("{$this->prefix}{$key}:-{$value}|c", $tags);
    }

    public function gauge(string $key, float|int $value, array $tags = []): void
    {
        $this->buffer("{$this->prefix}{$key}:{$value}|g", $tags);
    }

    public function timing(string $key, float $milliseconds, array $tags = []): void
    {
        $this->buffer("{$this->prefix}{$key}:{$milliseconds}|ms", $tags);
    }

    /**
     * Vide le buffer vers l'agent StatsD.
     * Groupe les métriques en datagrammes <= maxDatagramSize.
     */
    public function flush(): void
    {
        if (empty($this->buffer)) {
            return;
        }

        $datagram = '';

        foreach ($this->buffer as $line) {
            if (strlen($datagram) + strlen($line) + 1 > $this->maxDatagramSize) {
                $this->send($datagram);
                $datagram = '';
            }
            $datagram .= ($datagram === '' ? '' : "\n") . $line;
        }

        if ($datagram !== '') {
            $this->send($datagram);
        }

        $this->buffer = [];
    }

    public function pending(): int
    {
        return count($this->buffer);
    }

    public function all(): array { return []; }

    private function buffer(string $payload, array $tags): void
    {
        $line = $payload . $this->encodeTags($tags);

        $this->buffer[] = $line;

        // Flush automatique si le buffer atteint la taille max
        if (count($this->buffer) >= $this->maxBufferSize) {
            $this->flush();
        }
    }

    private function encodeTags(array $tags): string
    {
        if (empty($tags)) {
            return '';
        }

        $parts = [];
        foreach ($tags as $k => $v) {
            $parts[] = "{$k}:" . $this->sanitize((string) $v);
        }

        return '|#' . implode(',', $parts);
    }

    private function sanitize(string $value): string
    {
        // StatsD n'accepte pas les caractères réservés dans les tags
        return preg_replace('/[:|,@\n]/', '_', $value);
    }

    private function send(string $datagram): void
    {
        @fwrite($this->socket, $datagram . "\n");
    }
}