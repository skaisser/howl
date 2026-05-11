<?php

namespace Skaisser\Howl\Tests\Fixtures;

use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\Payload;

/**
 * Test-only driver that always fails — used to verify fallback chain behavior.
 * Can be configured to throw an exception or return false.
 */
class RaisingDriver implements Driver
{
    private bool $shouldThrow;

    private string $driverName;

    public function __construct(bool $shouldThrow = false, string $driverName = 'raising')
    {
        $this->shouldThrow = $shouldThrow;
        $this->driverName = $driverName;
    }

    public function name(): string
    {
        return $this->driverName;
    }

    public function send(Payload $payload): bool
    {
        if ($this->shouldThrow) {
            throw new \RuntimeException("RaisingDriver [{$this->driverName}] always throws");
        }

        return false;
    }
}
