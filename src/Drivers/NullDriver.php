<?php

namespace Skaisser\Howl\Drivers;

use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\Payload;

/**
 * No-op driver for testing and CI environments.
 * Records every payload sent so tests can assert on them.
 */
class NullDriver implements Driver
{
    /** @var Payload[] */
    public array $sent = [];

    public function name(): string
    {
        return 'null';
    }

    public function send(Payload $payload): bool
    {
        $this->sent[] = $payload;

        return true;
    }

    public function last(): ?Payload
    {
        return empty($this->sent) ? null : end($this->sent);
    }

    public function reset(): void
    {
        $this->sent = [];
    }
}
