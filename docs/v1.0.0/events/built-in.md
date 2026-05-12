# Built-in Events

Howl ships seven production-ready event templates for the most common observability scenarios. Use them directly or subclass them to add domain-specific data.

## GenericExceptionEvent

**Severity:** `error` | **Default Channel:** `errors`

Wraps any PHP `\Throwable` into a rich notification with class name as title, message as description, and stack trace as a code block.

```php
use Skaisser\Howl\Events\GenericExceptionEvent;

try {
    $this->processPayment($order);
} catch (\Throwable $e) {
    Howl::error(new GenericExceptionEvent($e));
}
```

**What it renders:**
- **Title**: Exception class name (e.g. `Illuminate\Database\QueryException`)
- **Description**: Exception message
- **Code block**: Truncated stack trace
- **Footer**: Exception class + line number

## GenericInfoEvent

**Severity:** configurable | **Default Channel:** severity-based

A flexible event for informational notifications where the severity varies by context. The `channel()` method maps severity to a channel name automatically:

| Severity | Default Channel |
|---|---|
| `error` | `errors` |
| `warning` | `warnings` |
| `info`, `success` | `info` |
| `audit` | `audit` |
| `deployment` | `deployments` |

```php
use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Facades\Howl;

// Use with a severity terminal verb
$event = new GenericInfoEvent;
Howl::info($event->title('Cache Warmed')->description('All cache keys populated.'));

// Or build the event then pass it
Howl::success(new GenericInfoEvent);
```

Most commonly used as a base class for quick one-off events.

## AuditEvent

**Severity:** `audit` | **Default Channel:** `audit`

Records who did what and when. Ideal for sensitive operations in admin panels, billing, or user management.

```php
use Skaisser\Howl\Events\AuditEvent;

Howl::audit(new AuditEvent(
    user: $request->user(),
    action: 'billing.subscription.cancelled',
    meta: [
        'plan'   => $subscription->plan,
        'reason' => $request->input('reason'),
    ]
));
```

**What it renders:**
- **Title**: `Audit: {action}`
- **Fields**: User email, user ID, action name
- **Footer meta**: Additional `meta` array merged into footer

## DeploymentEvent

**Severity:** `deployment` | **Default Channel:** `deployments`

Announces a deployment to production or staging.

```php
use Skaisser\Howl\Events\DeploymentEvent;

Howl::deployment(new DeploymentEvent(
    version: 'v1.2.0',
    environment: 'production',
    deployedBy: 'GitHub Actions',
    links: [
        ['label' => 'Release Notes', 'url' => 'https://github.com/acme/app/releases/tag/v1.2.0'],
    ]
));
```

**What it renders:**
- **Title**: `Deployed: {version} → {environment}`
- **Fields**: Version, environment, deployed-by
- **Buttons**: Links from the `$links` array

## CronHeartbeatEvent

**Severity:** `info` | **Default Channel:** `info`

Confirms a scheduled task ran on time. Useful with dead-man's-switch monitoring or for auditing long-running cron jobs.

```php
use Skaisser\Howl\Events\CronHeartbeatEvent;

// In a scheduled command's handle() method:
Howl::info(new CronHeartbeatEvent(
    name: 'send-weekly-digests',
    duration: $durationSeconds,
    processedCount: $emailsSent,
));
```

**What it renders:**
- **Title**: `Heartbeat: {name}`
- **Fields**: Duration, processed count, timestamp

## JobRetryExhaustedEvent

**Severity:** `error` | **Default Channel:** `errors`

Alerts when a Laravel queue job exhausts all its retry attempts. Pair this with a `failed()` method on your job class.

```php
use Skaisser\Howl\Events\JobRetryExhaustedEvent;

class ProcessPaymentJob implements ShouldQueue
{
    public int $tries = 3;

    public function failed(\Throwable $exception): void
    {
        Howl::error(new JobRetryExhaustedEvent(
            job: $this,
            exception: $exception,
        ));
    }
}
```

**What it renders:**
- **Title**: `Job Failed: {job class}`
- **Description**: Exception message
- **Fields**: Job class, queue, attempt count
- **Code block**: Stack trace

## ManualOperationEvent

**Severity:** `info` (configurable) | **Default Channel:** `info`

For ad-hoc operational notifications triggered by admin commands, scripts, or manual interventions.

```php
use Skaisser\Howl\Events\ManualOperationEvent;

// In an Artisan command
Howl::info(new ManualOperationEvent(
    title: 'Manual DB Cleanup Completed',
    description: 'Deleted 12,345 orphaned records from the `sessions` table.',
    operator: auth()->user()?->email ?? 'CLI',
));
```

**What it renders:**
- **Title**: Custom title
- **Description**: Custom description
- **Fields**: Operator, timestamp

## Extending Built-in Events

All built-in events are concrete classes — subclass them to add domain-specific fields:

```php
use Skaisser\Howl\Events\GenericExceptionEvent;

class PaymentExceptionEvent extends GenericExceptionEvent
{
    public function __construct(
        \Throwable $exception,
        private readonly Payment $payment,
    ) {
        parent::__construct($exception);
    }

    public function fields(): array
    {
        return array_merge(parent::fields(), [
            ['name' => 'Payment ID', 'value' => (string) $this->payment->id, 'inline' => true],
            ['name' => 'Amount',     'value' => '$' . $this->payment->amount, 'inline' => true],
        ]);
    }

    public function channel(): ?string
    {
        return 'payments';
    }
}
```
