# Custom Drivers

Howl's driver system is open. You can ship notifications to any service — PagerDuty, Opsgenie, a custom webhook, even a database — by implementing the `Skaisser\Howl\Contracts\Driver` interface and registering it with the Howl service provider.

## The Driver contract

```php
namespace Skaisser\Howl\Contracts;

use Skaisser\Howl\Support\Payload;

interface Driver
{
    /**
     * Driver name: unique lowercase identifier used in config + ->driver() calls.
     */
    public function name(): string;

    /**
     * Attempt to send the payload.
     * Return true on success, false on transport failure.
     * Throwing is allowed — the dispatcher catches \Throwable and logs it.
     */
    public function send(Payload $payload): bool;
}
```

Two methods. That's the entire contract.

## Minimal example: NullDriver

The built-in `NullDriver` is the simplest possible implementation — it records sent payloads and always returns `true`. Use it as a template:

```php
namespace Skaisser\Howl\Drivers;

use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\Payload;

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
}
```

## Real example: PagerDuty driver

```php
namespace App\Howl\Drivers;

use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Contracts\Driver;
use Skaisser\Howl\Support\Payload;

class PagerDutyDriver implements Driver
{
    public function name(): string
    {
        return 'pagerduty';
    }

    public function send(Payload $payload): bool
    {
        $response = Http::withToken(config('services.pagerduty.token'))
            ->post('https://events.pagerduty.com/v2/enqueue', [
                'routing_key'  => config('services.pagerduty.routing_key'),
                'event_action' => 'trigger',
                'payload' => [
                    'summary'   => $payload->title,
                    'severity'  => $this->mapSeverity($payload->severity),
                    'source'    => $payload->app ?? config('app.name'),
                    'timestamp' => now()->toIso8601String(),
                    'custom_details' => [
                        'description' => $payload->description,
                        'channel'     => $payload->channel,
                    ],
                ],
            ]);

        return $response->successful();
    }

    private function mapSeverity(string $severity): string
    {
        return match ($severity) {
            'error'      => 'critical',
            'warning'    => 'warning',
            'deployment' => 'info',
            default      => 'info',
        };
    }
}
```

## Registering a custom driver

Override `resolveDriver()` in a subclass of `Skaisser\Howl\Howl`, or — more practically — register it via your app's service provider by extending the Howl container binding:

```php
namespace App\Providers;

use App\Howl\Drivers\PagerDutyDriver;
use Illuminate\Support\ServiceProvider;
use Skaisser\Howl\Howl;

class HowlExtensionProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->extend('howl', function (Howl $howl) {
            // Monkey-patch resolveDriver so 'pagerduty' resolves to our class.
            // Howl resolves drivers on-demand, so this works for per-call ->driver() too.
            return new class($howl->getConfig()) extends Howl {
                public function resolveDriver(string $name): \Skaisser\Howl\Contracts\Driver
                {
                    if ($name === 'pagerduty') {
                        return new PagerDutyDriver;
                    }

                    return parent::resolveDriver($name);
                }
            };
        });
    }
}
```

Register the provider in `bootstrap/providers.php`:

```php
return [
    App\Providers\HowlExtensionProvider::class,
];
```

## Using the custom driver

Once registered, use the driver anywhere via the `->driver()` builder or the `howl.driver` config key:

```php
// Per-call override
Howl::driver('pagerduty')->error('Database unreachable');

// Or set as the default driver in config/howl.php
'driver' => 'pagerduty',
```

## Fallback chain

Custom drivers participate in the fallback chain just like built-in ones:

```php
// Try pagerduty; if it fails, fall back to discord
Howl::driver('pagerduty')->withFallback('discord')->error('Incident triggered');
```

## Testing a custom driver

Use `HowlFake` to assert your code routes to the custom driver without calling the real API:

```php
it('routes critical errors to pagerduty', function () {
    $fake = Howl::fake();

    Howl::driver('pagerduty')->error('Database unreachable');

    $fake->assertSentVia('pagerduty', fn ($p) => $p->severity === 'error');
});
```
