<?php

namespace Clicalmani\Metrics\Tests\Sampling;

use Clicalmani\Metrics\Sampling\NeverSampler;
use Clicalmani\Metrics\Sampling\PatternSampler;
use PHPUnit\Framework\TestCase;

final class PatternSamplerTest extends TestCase
{
    public function test_rate_one_always_samples_matching_keys(): void
    {
        $sampler = new PatternSampler(['db.*' => 1.0]);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($sampler->shouldSample('db.query'));
        }
    }

    public function test_patterns_use_fnmatch_wildcards(): void
    {
        $sampler = new PatternSampler([
            'db.*'         => 1.0,
            'http.*'       => 1.0,
            '*critical*'   => 1.0,
        ]);

        $this->assertTrue($sampler->shouldSample('db.query'));
        $this->assertTrue($sampler->shouldSample('http.request.count'));
        $this->assertTrue($sampler->shouldSample('my.critical.metric'));
    }

    public function test_unmatched_keys_fall_back_to_always_sampler_by_default(): void
    {
        $sampler = new PatternSampler(['db.*' => 1.0]);

        $this->assertTrue($sampler->shouldSample('queue.jobs'));
    }

    public function test_custom_fallback_is_used_for_unmatched_keys(): void
    {
        $sampler = new PatternSampler(['db.*' => 1.0], new NeverSampler());

        $this->assertTrue($sampler->shouldSample('db.query'));
        $this->assertFalse($sampler->shouldSample('queue.jobs'));
    }

    public function test_rate_zero_never_samples(): void
    {
        $sampler = new PatternSampler(['debug.*' => 0.0]);

        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($sampler->shouldSample('debug.chatter'));
        }
    }

    public function test_first_matching_rule_wins(): void
    {
        $sampler = new PatternSampler([
            'db.*'      => 1.0,
            'db.slow*'  => 0.0,
        ]);

        for ($i = 0; $i < 50; $i++) {
            $this->assertTrue($sampler->shouldSample('db.slow.query'));
        }
    }
}
