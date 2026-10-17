<?php

namespace Clicalmani\Metrics\Tests\Support;

final class FakeLogger
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function info(string $message, array $context = []): void
    {
        $this->records[] = ['message' => $message, 'context' => $context];
    }
}
