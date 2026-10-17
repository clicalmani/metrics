<?php

namespace Clicalmani\Metrics\Sampling;

interface Sampler
{
    /**
     * Décide si une métrique doit être émise.
     */
    public function shouldSample(string $key): bool;
}