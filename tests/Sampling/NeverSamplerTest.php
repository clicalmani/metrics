<?php

namespace Clicalmani\Metrics\Tests\Sampling;

use Clicalmani\Metrics\Sampling\NeverSampler;
use PHPUnit\Framework\TestCase;

final class NeverSamplerTest extends TestCase
{
    public function test_never_samples_any_key(): void
    {
        $sampler = new NeverSampler();

        $this->assertFalse($sampler->shouldSample('db.query'));
        $this->assertFalse($sampler->shouldSample('http.request'));
        $this->assertFalse($sampler->shouldSample(''));
    }
}
