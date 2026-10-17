<?php

namespace Clicalmani\Metrics\Contracts;

interface FlushableDriver extends MetricsDriver
{
    /**
     * Vide le buffer vers le backend.
     */
    public function flush(): void;

    /**
     * Nombre d'entrées en attente dans le buffer.
     */
    public function pending(): int;
}