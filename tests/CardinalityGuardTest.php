<?php

namespace Clicalmani\Metrics\Tests;

use Clicalmani\Metrics\CardinalityGuard;
use PHPUnit\Framework\TestCase;

final class CardinalityGuardTest extends TestCase
{
    public function test_disabled_guard_allows_everything_and_tracks_nothing(): void
    {
        $guard = new CardinalityGuard(0);

        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
        $this->assertTrue($guard->allow('requests', ['route' => 'b']));
        $this->assertTrue($guard->allow('requests', ['route' => 'c']));

        $this->assertSame(0, $guard->count('requests'));
    }

    public function test_allows_up_to_limit_then_drops(): void
    {
        $guard = new CardinalityGuard(2);

        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
        $this->assertTrue($guard->allow('requests', ['route' => 'b']));
        $this->assertFalse($guard->allow('requests', ['route' => 'c']));
        $this->assertFalse($guard->allow('requests', ['route' => 'd']));
    }

    public function test_known_series_still_allowed_after_limit_reached(): void
    {
        $guard = new CardinalityGuard(1);

        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
        $this->assertFalse($guard->allow('requests', ['route' => 'b']));
        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
    }

    public function test_limit_is_per_metric_key(): void
    {
        $guard = new CardinalityGuard(1);

        $this->assertTrue($guard->allow('requests', ['route' => 'a']));
        $this->assertTrue($guard->allow('jobs', ['queue' => 'q1']));
        $this->assertFalse($guard->allow('requests', ['route' => 'b']));
        $this->assertFalse($guard->allow('jobs', ['queue' => 'q2']));
    }

    public function test_on_drop_callback_receives_key_and_tags(): void
    {
        $dropped = [];
        $guard = new CardinalityGuard(1, function (string $key, array $tags) use (&$dropped) {
            $dropped[] = [$key, $tags];
        });

        $guard->allow('requests', ['route' => 'a']);
        $guard->allow('requests', ['route' => 'a']);
        $guard->allow('requests', ['route' => 'b']);
        $guard->allow('requests', ['route' => 'c']);

        $this->assertSame([
            ['requests', ['route' => 'b']],
            ['requests', ['route' => 'c']],
        ], $dropped);
    }

    public function test_reset_clears_known_series(): void
    {
        $guard = new CardinalityGuard(1);

        $guard->allow('requests', ['route' => 'a']);
        $guard->reset();

        $this->assertSame(0, $guard->count('requests'));
        $this->assertTrue($guard->allow('requests', ['route' => 'b']));
    }

    public function test_count_returns_known_series_per_key(): void
    {
        $guard = new CardinalityGuard(10);

        $guard->allow('requests', ['route' => 'a']);
        $guard->allow('requests', ['route' => 'b']);
        $guard->allow('jobs', ['queue' => 'q']);

        $this->assertSame(2, $guard->count('requests'));
        $this->assertSame(1, $guard->count('jobs'));
        $this->assertSame(0, $guard->count('unknown'));
    }
}
