<?php

namespace Skaisser\Howl\Contracts;

use Skaisser\Howl\Support\Payload;

interface Driver
{
    /**
     * Driver name: 'discord', 'telegram', 'slack', 'null'.
     */
    public function name(): string;

    /**
     * Attempt to send the payload. Return true on success, false on
     * transport failure. Throwing is allowed; the dispatcher catches.
     */
    public function send(Payload $payload): bool;
}
