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
     * Opt-in queue rate-limit middleware.
     *
     * When config('howl.rate_limiter_key') is non-null, wraps each job with
     * RateLimitedWithRedis using that key. Rate-limit releases do NOT count
     * against $tries. When null (default), no throttling is applied.
     *
     * Register the limiter in AppServiceProvider::boot():
     *   RateLimiter::for('howl-discord', fn () => Limit::perMinute(28));
     */
    public function middleware(): array
    {
        $key = config('howl.rate_limiter_key');

        return $key !== null
            ? [new \Illuminate\Queue\Middleware\RateLimitedWithRedis($key)]
            : [];
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
