<?php

namespace Skaisser\Howl\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Skaisser\Howl\Support\PendingNotification on(?string $channel = null)
 * @method static \Skaisser\Howl\Support\PendingNotification driver(string $name)
 * @method static bool error(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static bool warning(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static bool info(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static bool audit(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static bool deployment(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static bool success(\Skaisser\Howl\Events\HowlEvent|string $titleOrEvent = '')
 * @method static \Skaisser\Howl\Testing\HowlFake fake()
 * @method static void assertNothingSent()
 * @method static void assertSent(callable $callback)
 * @method static void assertSentEvent(string $eventClass)
 * @method static void assertSentOnChannel(string $channel, callable $callback)
 * @method static void assertSentVia(string $driver, callable $callback)
 * @method static void assertSentViaNothing(string $driver)
 * @method static array sent(?string $channel = null)
 * @method static array sentVia(string $driver)
 * @method static bool dispatch(\Skaisser\Howl\Support\Payload $payload)
 *
 * @see \Skaisser\Howl\Howl
 */
class Howl extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'howl';
    }
}
