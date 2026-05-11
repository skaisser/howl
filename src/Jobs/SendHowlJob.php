<?php

namespace Skaisser\Howl\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Skaisser\Howl\Howl;
use Skaisser\Howl\Support\Payload;

class SendHowlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly Payload $payload,
        public readonly string $driverName,
    ) {}

    public function backoff(): array
    {
        return [1, 4, 16];
    }

    /**
     * Execute the job. Resolves the driver from the Howl instance and calls send().
     * On false return or exception, lets the queue retry (throws RuntimeException).
     */
    public function handle(Howl $howl): void
    {
        $driver = $howl->resolveDriver($this->driverName);
        $result = $driver->send($this->payload);

        if (! $result) {
            throw new \RuntimeException("Howl driver [{$this->driverName}] returned false");
        }
    }
}
