<?php

namespace Skaisser\Howl\Testing;

use PHPUnit\Framework\Assert;
use Skaisser\Howl\Howl;
use Skaisser\Howl\Support\Payload;

/**
 * Drop-in replacement for Howl that captures dispatched payloads instead of
 * sending them over the wire. Install via Howl::fake() at the top of a test.
 */
class HowlFake extends Howl
{
    /** @var array<string, Payload[]> Payloads indexed by channel name. */
    protected array $sent = [];

    /**
     * Capture the payload instead of dispatching to the real driver.
     * Intentionally skips the skip_environments check so tests can always
     * assert sends, even when APP_ENV is in skip_environments.
     */
    public function dispatch(Payload $payload): bool
    {
        $channel = $payload->channel ?? 'default';
        $this->sent[$channel][] = $payload;

        return true;
    }

    /**
     * Assert that at least one dispatched payload satisfies the callback.
     *
     * @param  callable(Payload): bool  $callback
     */
    public function assertSent(callable $callback): void
    {
        $all = $this->flattenSent();

        Assert::assertTrue(
            collect($all)->contains($callback),
            'No payload matched the given callback. Sent '.count($all).' payload(s) total.'
        );
    }

    /**
     * Assert that at least one payload dispatched to the given channel
     * satisfies the callback.
     *
     * @param  callable(Payload): bool  $callback
     */
    public function assertSentOnChannel(string $channel, callable $callback): void
    {
        $payloads = $this->sent[$channel] ?? [];

        Assert::assertTrue(
            collect($payloads)->contains($callback),
            "No payload matched the callback on channel '{$channel}'. Sent ".count($payloads).' payload(s) on that channel.'
        );
    }

    /**
     * Assert that at least one dispatched payload carries the given event name
     * in its meta['event'] key.
     *
     * Expected format: snake_case basename auto-injected by HowlEvent::baseFooterMeta()
     * — e.g. `'generic_exception'` for `GenericExceptionEvent`, `'order_shipped'` for
     * `OrderShippedEvent`. Pass the snake_case event name, NOT the FQCN.
     */
    public function assertSentEvent(string $eventName): void
    {
        $all = $this->flattenSent();

        $found = collect($all)->contains(
            fn (Payload $p) => ($p->meta['event'] ?? null) === $eventName
        );

        Assert::assertTrue(
            $found,
            "No payload was found with event '{$eventName}'."
        );
    }

    /**
     * Assert that no payloads have been dispatched.
     */
    public function assertNothingSent(): void
    {
        $total = count($this->flattenSent());

        Assert::assertSame(
            0,
            $total,
            "Expected no payloads to be sent, but {$total} payload(s) were captured."
        );
    }

    /**
     * Return captured payloads.
     *
     * @return Payload[]
     */
    public function sent(?string $channel = null): array
    {
        if ($channel !== null) {
            return $this->sent[$channel] ?? [];
        }

        return $this->flattenSent();
    }

    // -----------------------------------------------------------------------
    // Internal
    // -----------------------------------------------------------------------

    /** @return Payload[] */
    protected function flattenSent(): array
    {
        return array_merge(...array_values($this->sent) ?: [[]]);
    }
}
