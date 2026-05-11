<?php

namespace Skaisser\Howl\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Skaisser\Howl\Support\PendingNotification onDiscord(?string $channel = null)
 * @method static \Skaisser\Howl\Support\PendingNotification onSlack(?string $channel = null)
 * @method static \Skaisser\Howl\Support\PendingNotification onTelegram(?string $channel = null)
 * @method static \Skaisser\Howl\Testing\HowlFake fake()
 * @method static void assertSent(callable $callback)
 * @method static void assertSentOnChannel(string $channel, callable $callback)
 * @method static void assertSentEvent(string $eventClass)
 * @method static void assertNothingSent()
 * @method static array sent(?string $channel = null)
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
