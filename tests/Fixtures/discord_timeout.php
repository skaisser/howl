<?php

use Illuminate\Http\Client\ConnectionException;

/**
 * Returns a closure that throws a ConnectionException to simulate a Discord webhook timeout.
 *
 * Usage in tests:
 *   $timeout = require __DIR__.'/discord_timeout.php';
 *   Http::fake(['*' => $timeout()]);
 */
return static function (): never {
    throw new ConnectionException('cURL error 28: Operation timed out after 10000 milliseconds');
};
