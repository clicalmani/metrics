<?php

namespace Clicalmani\Metrics\Tests\Drivers;

use Clicalmani\Metrics\Drivers\LogDriver;
use Clicalmani\Metrics\Tests\Support\FakeLogger;
use PHPUnit\Framework\TestCase;

final class LogDriverTest extends TestCase
{
    private FakeLogger $logger;

    private LogDriver $driver;

    protected function setUp(): void
    {
        $this->logger = new FakeLogger();
        $this->driver = new LogDriver($this->logger);
    }

    public function test_increment_logs_counter_with_value_and_tags(): void
    {
        $this->driver->increment('hits', 3, ['env' => 'test']);

        $this->assertSame([
            ['message' => 'metric.counter.hits', 'context' => ['value' => 3, 'env' => 'test']],
        ], $this->logger->records);
    }

    public function test_decrement_logs_negative_value(): void
    {
        $this->driver->decrement('cart', 2);

        $this->assertSame([
            ['message' => 'metric.counter.cart', 'context' => ['value' => -2]],
        ], $this->logger->records);
    }

    public function test_gauge_logs_value(): void
    {
        $this->driver->gauge('memory', 512);

        $this->assertSame([
            ['message' => 'metric.gauge.memory', 'context' => ['value' => 512]],
        ], $this->logger->records);
    }

    public function test_timing_logs_milliseconds(): void
    {
        $this->driver->timing('db.query', 12.5);

        $this->assertSame([
            ['message' => 'metric.timing.db.query', 'context' => ['ms' => 12.5]],
        ], $this->logger->records);
    }
}
