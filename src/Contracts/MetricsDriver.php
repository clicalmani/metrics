<?php

namespace Clicalmani\Metrics\Contracts;

interface MetricsDriver
{
    /**
     * Incrémente un compteur.
     */
    public function increment(string $key, int $value = 1, array $tags = []): void;

    /**
     * Décrémente un compteur.
     */
    public function decrement(string $key, int $value = 1, array $tags = []): void;

    /**
     * Définit une valeur ponctuelle (gauge).
     */
    public function gauge(string $key, float|int $value, array $tags = []): void;

    /**
     * Enregistre une durée (en ms).
     */
    public function timing(string $key, float $milliseconds, array $tags = []): void;

    /**
     * Récupère toutes les métriques (utile pour tests / dump).
     *
     * @return array<string, mixed>
     */
    public function all(): array;

    /**
     * Réinitialise le driver.
     */
    public function flush(): void;
}