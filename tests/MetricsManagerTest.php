<?php

namespace Clicalmani\Metrics\Tests;

use Clicalmani\Metrics\CardinalityGuard;
use Clicalmani\Metrics\Drivers\InMemoryDriver;
use Clicalmani\Metrics\Drivers\LogDriver;
use Clicalmani\Metrics\MetricsManager;
use Clicalmani\Metrics\Sampling\NeverSampler;
use Clicalmani\Metrics\Tests\Support\FakeLogger;
use Clicalmani\Metrics\Tests\Support\FlushableSpyDriver;
use Clicalmani\Metrics\Tests\Support\SpyDriver;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MetricsManagerTest extends TestCase
{
    private SpyDriver $spy;

    private MetricsManager $manager;

    protected function setUp(): void
    {
        $this->spy = new SpyDriver();
        $this->manager = new MetricsManager($this->spy);
    }

    public function test_defaults_to_in_memory_driver(): void
    {
        $manager = new MetricsManager();

        $this->assertInstanceOf(InMemoryDriver::class, $manager->driver());
    }

    public function test_increment_forwards_key_value_and_merged_tags(): void
    {
        $this->manager->defaultTags(['app' => 'tonka']);

        $result = $this->manager->increment('hits', 2, ['env' => 'prod']);

        $this->assertSame($this->manager, $result);
        $this->assertSame([
            ['increment', 'hits', 2, ['app' => 'tonka', 'env' => 'prod']],
        ], $this->spy->calls);
    }

    public function test_call_tags_override_default_tags(): void
    {
        $this->manager->defaultTags(['app' => 'default']);

        $this->manager->increment('hits', 1, ['app' => 'override']);

        $this->assertSame([
            ['increment', 'hits', 1, ['app' => 'override']],
        ], $this->spy->calls);
    }

    public function test_default_tags_are_replaced_not_merged(): void
    {
        $this->manager->defaultTags(['a' => 1]);
        $this->manager->defaultTags(['b' => 2]);

        $this->manager->increment('hits');

        $this->assertSame([
            ['increment', 'hits', 1, ['b' => 2]],
        ], $this->spy->calls);
    }

    public function test_decrement_gauge_and_timing_are_forwarded(): void
    {
        $this->manager->decrement('cart', 3, ['t' => 1]);
        $this->manager->gauge('memory', 512.5);
        $this->manager->timing('db.query', 12.5);

        $this->assertSame([
            ['decrement', 'cart', 3, ['t' => 1]],
            ['gauge', 'memory', 512.5, []],
            ['timing', 'db.query', 12.5, []],
        ], $this->spy->calls);
    }

    public function test_measure_returns_callback_result_and_records_timing(): void
    {
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        $result = $manager->measure('api.call', function () {
            usleep(1000);

            return 42;
        });

        $this->assertSame(42, $result);

        $timings = $driver->all()['timings'];

        $this->assertArrayHasKey('api.call', $timings);
        $this->assertIsFloat($timings['api.call'][0]);
        $this->assertGreaterThan(0.0, $timings['api.call'][0]);
    }

    public function test_measure_records_timing_even_when_callback_throws(): void
    {
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        try {
            $manager->measure('api.call', static fn () => throw new RuntimeException('boom'));
            $this->fail('L\'exception du callback devait remonter.');
        } catch (RuntimeException) {
        }

        $timings = $driver->all()['timings'];

        $this->assertArrayHasKey('api.call', $timings);
        $this->assertCount(1, $timings['api.call']);
        $this->assertGreaterThan(0.0, $timings['api.call'][0]);
    }

    public function test_with_tags_merges_tags_inside_scope_and_restores_them_after(): void
    {
        $this->manager->defaultTags(['app' => 'tonka']);

        $returned = $this->manager->withTags(['tenant' => 'acme'], function (MetricsManager $m) {
            $m->increment('requests');

            return 'done';
        });

        $this->assertSame('done', $returned);

        $this->manager->increment('requests');

        $this->assertSame([
            ['increment', 'requests', 1, ['app' => 'tonka', 'tenant' => 'acme']],
            ['increment', 'requests', 1, ['app' => 'tonka']],
        ], $this->spy->calls);
    }

    public function test_with_tags_restores_defaults_on_exception(): void
    {
        $this->manager->defaultTags(['app' => 'tonka']);

        try {
            $this->manager->withTags(['tenant' => 'acme'], static function (): never {
                throw new RuntimeException('boom');
            });
            $this->fail('L\'exception du callback devait remonter.');
        } catch (RuntimeException) {
        }

        $this->manager->increment('requests');

        $this->assertSame([
            ['increment', 'requests', 1, ['app' => 'tonka']],
        ], $this->spy->calls);
    }

    public function test_never_sampler_suppresses_emission(): void
    {
        $this->manager->sampler(new NeverSampler());

        $result = $this->manager->increment('hits');
        $this->manager->gauge('memory', 10);

        $this->assertSame($this->manager, $result);
        $this->assertSame([], $this->spy->calls);
    }

    public function test_sampling_rules_apply_by_pattern(): void
    {
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        $result = $manager->sampling(['db.*' => 1.0]);

        $this->assertSame($manager, $result);
        $manager->increment('db.query');
        $manager->increment('http.request');

        $counters = $driver->all()['counters'];

        $this->assertSame(1, $counters['db.query']);
        $this->assertSame(1, $counters['http.request']);
    }

    public function test_cardinality_limit_drops_new_series_beyond_limit(): void
    {
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        $dropped = [];
        $manager->cardinalityLimit(2, function (string $key, array $tags) use (&$dropped) {
            $dropped[] = [$key, $tags];
        });

        $manager->increment('requests', tags: ['route' => 'a']);
        $manager->increment('requests', tags: ['route' => 'b']);
        $manager->increment('requests', tags: ['route' => 'c']);
        $manager->increment('requests', tags: ['route' => 'a']);

        $counters = $driver->all()['counters'];

        $this->assertSame(2, $counters['requests[route=a]']);
        $this->assertSame(1, $counters['requests[route=b]']);
        $this->assertArrayNotHasKey('requests[route=c]', $counters);
        $this->assertSame([['requests', ['route' => 'c']]], $dropped);
    }

    public function test_cardinality_accessor_exposes_active_guard(): void
    {
        $this->manager->cardinalityLimit(2);

        $this->assertInstanceOf(CardinalityGuard::class, $this->manager->cardinality());

        $this->manager->increment('requests', tags: ['route' => 'a']);
        $this->manager->increment('requests', tags: ['route' => 'b']);

        $this->assertSame(2, $this->manager->cardinality()->count('requests'));
    }

    public function test_flush_is_disabled_by_default(): void
    {
        $this->manager->increment('a');
        $this->manager->increment('b');

        $this->assertSame(0, $this->spy->flushes);
    }

    public function test_manual_flush_calls_driver_flush(): void
    {
        $result = $this->manager->flush();

        $this->assertSame($this->manager, $result);
        $this->assertSame(1, $this->spy->flushes);
    }

    public function test_flush_interval_triggers_flush_on_first_emission_after_delay(): void
    {
        $this->manager->flushInterval(1);

        $this->manager->increment('a');
        $this->assertSame(0, $this->spy->flushes);

        sleep(1);

        $this->manager->increment('b');
        $this->assertSame(1, $this->spy->flushes);
    }

    public function test_pending_delegates_to_flushable_driver(): void
    {
        $driver = new FlushableSpyDriver();
        $manager = new MetricsManager();
        $manager->setDriver($driver);

        $manager->increment('a');
        $manager->increment('b');

        $this->assertSame(2, $manager->pending());

        $manager->flush();

        $this->assertSame(0, $manager->pending());
        $this->assertSame(1, $driver->flushes);
    }

    public function test_pending_is_zero_for_non_flushable_driver(): void
    {
        $manager = new MetricsManager(new InMemoryDriver());

        $manager->increment('a');

        $this->assertSame(0, $manager->pending());
    }

    public function test_set_driver_replaces_driver(): void
    {
        $driver = new InMemoryDriver();

        $result = $this->manager->setDriver($driver);

        $this->assertSame($this->manager, $result);
        $this->assertSame($driver, $this->manager->driver());
    }

    public function test_use_instantiates_driver_with_constructor_args(): void
    {
        $logger = new FakeLogger();

        $this->manager->use(LogDriver::class, $logger);

        $this->assertInstanceOf(LogDriver::class, $this->manager->driver());

        $this->manager->increment('hits');

        $this->assertCount(1, $logger->records);
    }

    public function test_all_delegates_to_driver(): void
    {
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        $manager->increment('hits');

        $this->assertSame($driver->all(), $manager->all());
    }

    public function test_init_applies_default_tags_and_sampling(): void
    {
        // cardinalityLimit > 0 exige le helper logger() du framework Tonka,
        // absent de ce package : on teste ici les chemins sans dépendance.
        $driver = new InMemoryDriver();
        $manager = new MetricsManager($driver);

        $manager->__init__(['app' => 'tonka'], ['db.*' => 1.0], 0, 0);

        $manager->increment('db.query');

        $this->assertSame(1, $driver->all()['counters']['db.query[app=tonka]']);
    }
}
