<?php

namespace Clicalmani\Metrics;

final class CardinalityGuard
{
    /** @var array<string, array<string, true>>  [metricKey => [tagHash => true]] */
    private array $seen = [];

    public function __construct(
        private int $limit = 0,           // 0 = désactivé
        private ?\Closure $onDrop = null, // callback quand une série est rejetée
    ) {}

    /**
     * Retourne true si la série peut être émise, false si elle dépasse le quota.
     */
    public function allow(string $metricKey, array $tags): bool
    {
        if ($this->limit === 0) {
            return true;
        }

        $hash = md5(serialize($tags));

        if (isset($this->seen[$metricKey][$hash])) {
            return true; // déjà connue, on autorise
        }

        if (count($this->seen[$metricKey] ?? []) >= $this->limit) {
            if ($this->onDrop) {
                ($this->onDrop)($metricKey, $tags);
            }
            return false;
        }

        $this->seen[$metricKey][$hash] = true;

        return true;
    }

    public function reset(): void
    {
        $this->seen = [];
    }

    public function count(string $metricKey): int
    {
        return count($this->seen[$metricKey] ?? []);
    }
}