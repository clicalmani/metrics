<?php

namespace Clicalmani\Metrics\Tests\Sampling;

use Clicalmani\Metrics\Sampling\AlwaysSampler;
use PHPUnit\Framework\TestCase;

final class AlwaysSamplerTest extends TestCase
{
    public function test_samples_every_key(): void
    {
        $sampler = new AlwaysSampler();

        $this->assertTrue($sampler->shouldSample('db.query'));
        $this->assertTrue($sampler->shouldSample('http.request'));
        $this->assertTrue($sampler->shouldSample(''));
    }
}
