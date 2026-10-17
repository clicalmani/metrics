<?php

namespace Clicalmani\Metrics\Tests\Drivers;

use Clicalmani\Metrics\Drivers\InMemoryDriver;
use PHPUnit\Framework\TestCase;

final class InMemoryDriverTest extends TestCase
{
    public function test_increment_accumulates(): void
    {
        $driver = new InMemoryDriver();

        $driver->increment('hits');
        $driver->increment('hits');
        $driver->increment('hits', 5);

        $this->assertSame(['hits' => 7], $driver->all()['counters']);
    }

    public function test_decrement_subtracts(): void
    {
        $driver = new InMemoryDriver();

        $driver->increment('cart', 10);
        $driver->decrement('cart', 3);
        $driver->decrement('cart');

        $this->assertSame(['cart' => 6], $driver->all()['counters']);
    }

    public function test_gauge_keeps_last_value(): void
    {
        $driver = new InMemoryDriver();

        $driver->gauge('memory', 512);
        $driver->gauge('memory', 1024);

        $this->assertSame(['memory' => 1024], $driver->all()['gauges']);
    }

    public function test_timing_collects_samples(): void
    {
        $driver = new InMemoryDriver();

        $driver->timing('db.query', 12.5);
        $driver->timing('db.query', 7.25);

        $this->assertSame(['db.query' => [12.5, 7.25]], $driver->all()['timings']);
    }

    public function test_tags_create_distinct_series(): void
    {
        $driver = new InMemoryDriver();

        $driver->increment('requests', tags: ['route' => 'home']);
        $driver->increment('requests', tags: ['route' => 'about']);

        $this->assertSame(
            ['requests[route=home]' => 1, 'requests[route=about]' => 1],
            $driver->all()['counters']
        );
    }

    public function test_tag_order_is_normalized(): void
    {
        $driver = new InMemoryDriver();

        $driver->increment('requests', tags: ['b' => 2, 'a' => 1]);
        $driver->increment('requests', tags: ['a' => 1, 'b' => 2]);

        $this->assertSame(['requests[a=1,b=2]' => 2], $driver->all()['counters']);
    }

    public function test_flush_keeps_data_intact(): void
    {
        $driver = new InMemoryDriver();

        $driver->increment('hits');
        $driver->flush();

        $this->assertSame(['hits' => 1], $driver->all()['counters']);
    }
}
