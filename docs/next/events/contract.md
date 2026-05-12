# HowlEvent Contract

`HowlEvent` is the abstract base class that all Howl events extend. It defines the 8-method contract that drivers use to render notifications, plus the `toPayload()` final method that orchestrates the rendering pipeline.

## Class Location

```
src/Events/HowlEvent.php
```

## The 8-Method Contract

All methods are abstract and must be implemented by concrete event classes (or inherit from `GenericInfoEvent`/`GenericExceptionEvent`).

### `severity(): string`

Returns the event's severity level. One of: `'error'`, `'warning'`, `'info'`, `'success'`, `'audit'`, `'deployment'`.

```php
public function severity(): string
{
    return 'error';
}
```

The severity determines the color, emoji, and username displayed by drivers.

### `title(): string`

Returns the notification title — the primary headline of the notification.

```php
public function title(): string
{
    return 'Payment Processing Failed';
}
```

### `description(): ?string`

Returns the notification body text, or `null` for no description. Supports plain text; Slack supports Markdown in descriptions.

```php
public function description(): ?string
{
    return "Failed to process payment #{$this->payment->id}.";
}
```

### `fields(): array`

Returns an array of `['name', 'value', 'inline']` triples for structured key/value data.

```php
public function fields(): array
{
    return [
        ['name' => 'Payment ID', 'value' => (string) $this->payment->id, 'inline' => true],
        ['name' => 'Amount',     'value' => '$' . $this->payment->amount, 'inline' => true],
        ['name' => 'Status',     'value' => $this->payment->status, 'inline' => false],
    ];
}
```

`inline: true` renders fields side-by-side in Discord. Slack renders all fields as a definition list regardless of the inline flag.

### `emoji(): string`

Returns the emoji used in the notification username and Telegram header. Defaults to the severity emoji from `config('howl.emojis')` when using built-in events.

```php
public function emoji(): string
{
    return '💳';
}
```

### `codeBlocks(): array`

Returns an array of `['name', 'code', 'lang']` triples for syntax-highlighted code blocks.

```php
public function codeBlocks(): array
{
    return [
        [
            'name' => 'Stack Trace',
            'code' => $this->exception->getTraceAsString(),
            'lang' => 'php',
        ],
    ];
}
```

### `footerMeta(): array`

Returns key/value pairs for the notification footer. Built-in events typically return the exception class or event class name here.

```php
public function footerMeta(): array
{
    return [
        'class' => get_class($this->exception),
    ];
}
```

### `channel(): ?string`

Returns the default channel name for this event, or `null` to defer to `config('howl.channel')`.

```php
public function channel(): ?string
{
    return 'payments'; // always routes to the 'payments' channel unless overridden per-call
}
```

Returning `null` defers to the per-call `Howl::on()` override or the config default.

## Universal Constructor

All built-in events share a universal constructor (inherited from `HowlEvent`):

```php
public function __construct(
    array $links = [],  // Action buttons: [['label' => 'text', 'url' => 'https://...']]
    array $meta  = [],  // Arbitrary key/value metadata appended to footer
)
```

Custom events don't need to use this constructor — define your own.

## `toPayload()` — The Orchestrator

`toPayload()` is `final` and assembles a `Payload` object from all 8 contract methods. You never call it directly; Howl calls it internally during dispatch.

## The Builder-State-Wins Rule

When a `PendingNotification` builder has pre-configured values AND the terminal verb receives a `HowlEvent`:

- **Builder scalar values WIN** on collision: `title`, `description`, `channel`
- **Builder-set fields are APPENDED** after event-supplied fields (not replaced)
- Builder `meta` is **merged** over event meta (builder wins on key collision)

```php
// Builder title wins: "Override Title" posted, not the event's title()
Howl::title('Override Title')->error(new GenericExceptionEvent($e));
```

Use `->send($event)` (no terminal verb severity mismatch check) when you want to defer completely to the event's severity, title, and fields without builder interference.
