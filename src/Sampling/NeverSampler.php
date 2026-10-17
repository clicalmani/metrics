<?php

namespace Clicalmani\Metrics\Sampling;

final class NeverSampler implements Sampler
{
    public function shouldSample(string $key): bool
    {
        return false;
    }
}