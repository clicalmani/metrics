<?php

namespace Clicalmani\Metrics\Tests\Drivers;

use Clicalmani\Metrics\Drivers\StatsdDriver;
use PHPUnit\Framework\TestCase;

final class StatsdDriverTest extends TestCase
{
    private \Socket $server;

    private string $host = '127.0.0.1';

    private int $port = 0;

    protected function setUp(): void
    {
        // stream_socket_server() ne supporte pas UDP sur ce build Windows,
        // on utilise l'extension sockets pour le serveur de réception.
        $server = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        $this->assertNotFalse($server, 'Impossible de créer le socket UDP de réception.');
        $this->assertTrue(socket_bind($server, $this->host, 0), 'Impossible de lier le socket UDP.');

        socket_getsockname($server, $addr, $this->port);

        $this->server = $server;
    }

    protected function tearDown(): void
    {
        if (isset($this->server)) {
            socket_close($this->server);
        }
    }

    private function driver(
        string $prefix = 'tonka.',
        int $maxBufferSize = 500,
        int $maxDatagramSize = 1432,
    ): StatsdDriver {
        return new StatsdDriver($this->host, $this->port, $prefix, $maxBufferSize, $maxDatagramSize);
    }

    private function receive(int $timeoutMs = 2000): ?string
    {
        socket_set_option($this->server, SOL_SOCKET, SO_RCVTIMEO, [
            'sec'  => intdiv($timeoutMs, 1000),
            'usec' => ($timeoutMs % 1000) * 1000,
        ]);

        $n = @socket_recvfrom($this->server, $buf, 65535, 0, $from, $fromPort);

        return ($n !== false && $n > 0) ? $buf : null;
    }

    public function test_increment_sends_counter_datagram_with_tags(): void
    {
        $driver = $this->driver();

        $driver->increment('hits', 3, ['env' => 'test']);
        $driver->flush();

        $this->assertSame("tonka.hits:3|c|#env:test\n", $this->receive());
    }

    public function test_decrement_sends_negative_counter(): void
    {
        $driver = $this->driver();

        $driver->decrement('cart', 2);
        $driver->flush();

        $this->assertSame("tonka.cart:-2|c\n", $this->receive());
    }

    public function test_gauge_sends_gauge_datagram(): void
    {
        $driver = $this->driver();

        $driver->gauge('memory', 512.5);
        $driver->flush();

        $this->assertSame("tonka.memory:512.5|g\n", $this->receive());
    }

    public function test_timing_sends_timing_datagram(): void
    {
        $driver = $this->driver();

        $driver->timing('db.query', 12.5);
        $driver->flush();

        $this->assertSame("tonka.db.query:12.5|ms\n", $this->receive());
    }

    public function test_tag_values_are_sanitized(): void
    {
        $driver = $this->driver();

        $driver->increment('hits', 1, ['user' => "a:b|c,d@e\nf"]);
        $driver->flush();

        $this->assertSame("tonka.hits:1|c|#user:a_b_c_d_e_f\n", $this->receive());
    }

    public function test_multiple_tags_are_encoded_in_order(): void
    {
        $driver = $this->driver();

        $driver->increment('hits', 1, ['env' => 'prod', 'service' => 'api']);
        $driver->flush();

        $this->assertSame("tonka.hits:1|c|#env:prod,service:api\n", $this->receive());
    }

    public function test_flush_groups_metrics_into_one_datagram(): void
    {
        $driver = $this->driver();

        $driver->increment('a', 1);
        $driver->increment('b', 2);
        $driver->gauge('c', 3);
        $driver->flush();

        $this->assertSame("tonka.a:1|c\ntonka.b:2|c\ntonka.c:3|g\n", $this->receive());
    }

    public function test_flush_splits_datagrams_exceeding_max_size(): void
    {
        // "aa:1|c" = 6 octets ; deux lignes jointes = 13 octets.
        $driver = $this->driver(prefix: '', maxDatagramSize: 13);

        $driver->increment('aa', 1);
        $driver->increment('aa', 1);
        $driver->increment('aa', 1);
        $driver->flush();

        $this->assertSame("aa:1|c\naa:1|c\n", $this->receive());
        $this->assertSame("aa:1|c\n", $this->receive());
    }

    public function test_buffer_auto_flushes_at_max_buffer_size(): void
    {
        $driver = $this->driver(maxBufferSize: 2);

        $driver->increment('a', 1);
        $this->assertSame(1, $driver->pending());

        $driver->increment('b', 1);
        $this->assertSame(0, $driver->pending());

        $this->assertSame("tonka.a:1|c\ntonka.b:1|c\n", $this->receive());
    }

    public function test_pending_counts_buffered_metrics(): void
    {
        $driver = $this->driver();

        $driver->increment('a', 1);
        $driver->increment('b', 2);

        $this->assertSame(2, $driver->pending());

        $driver->flush();

        $this->assertSame(0, $driver->pending());
    }

    public function test_flush_with_empty_buffer_sends_nothing(): void
    {
        $driver = $this->driver();

        $driver->flush();

        $this->assertNull($this->receive(200));
    }

    public function test_constructor_throws_when_host_is_unresolvable(): void
    {
        $caught = false;

        set_error_handler(static fn () => true);

        try {
            new StatsdDriver('host.invalid', 8125);
        } catch (\RuntimeException) {
            $caught = true;
        } finally {
            restore_error_handler();
        }

        $this->assertTrue($caught, 'StatsdDriver doit lever une RuntimeException quand la connexion échoue.');
    }
}
