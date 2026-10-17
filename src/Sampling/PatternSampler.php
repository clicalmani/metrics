<?php

namespace Clicalmani\Metrics\Sampling;

final class PatternSampler implements Sampler
{
    /**
     * @param array<string, float> $rules  [pattern => rate (0..1)]
     */
    public function __construct(
        private array $rules = [],
        private Sampler $fallback = new AlwaysSampler(),
    ) {}

    public function shouldSample(string $key): bool
    {
        foreach ($this->rules as $pattern => $rate) {
            if (fnmatch($pattern, $key)) {
                return (mt_rand() / mt_getrandmax()) <= $rate;
            }
        }

        return $this->fallback->shouldSample($key);
    }
}