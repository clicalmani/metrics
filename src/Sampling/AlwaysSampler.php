<?php

namespace Clicalmani\Metrics\Sampling;

final class AlwaysSampler implements Sampler
{
    public function shouldSample(string $key): bool
    {
        return true;
    }
}