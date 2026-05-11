# Extending Howl — App-Specific Event Templates

This guide is for developers who need domain-specific Discord notifications beyond what the seven generic templates cover. It walks through the `HowlEvent` extension contract (8 methods), the `links` convention, footer extension patterns, common pitfalls, and testing recipes.

**Reference:** For a full worked example with class, Pest test, dispatch site, and embed mockup, see [`docs/example-app-template.md`](example-app-template.md).

---

## 1. When to Extend vs Use a Generic Template

Howl ships seven generic templates that cover the most common observability patterns. Before writing a new subclass, ask:

> **Does my event need two or more fields that are specific to my domain?**

| Condition | Recommendation |
|---|---|
| One-off exception with a standard message | `GenericExceptionEvent($throwable)` |
| Informational event with a simple message | `GenericInfoEvent($title, $description)` |
| Actor performed an action on a target | `AuditEvent($actor, $action, $target)` |
| CI/CD deploy notification | `DeploymentEvent($version, $env, $commit)` |
| Cron health check | `CronHeartbeatEvent($name, $status)` |
| Laravel queued job hit max retries | `JobRetryExhaustedEvent($job, $exception)` |
| CLI / admin action with side effects | `ManualOperationEvent($operator, $action)` |
| **2+ domain fields + domain-specific emoji** | **Extend `HowlEvent`** |

The decision tree in brief:

```
Need ≥2 domain fields?
  ├── No  → pick the closest generic template
  └── Yes → extend HowlEvent
        └── Override all 8 contract methods + constructor
```

If you find yourself using `GenericInfoEvent` with four `->field()` overlays and a custom emoji, that is the signal to write a subclass — the boilerplate pays off in readability and testability.

---

## 2. The 8-Method Contract Walkthrough

Every `HowlEvent` subclass must implement the five abstract methods. Three additional methods have working defaults and may be overridden when needed. The abstract base class enforces the required five at compile time — a missing method throws a fatal error on first use.

### Universal constructor

```php
parent::__construct(array $links = [], array $meta = [])
```

Your subclass constructor receives domain data as typed parameters, then chains to `parent::__construct($links, $meta)`. The `$links` and `$meta` arrays are consumed by `renderLinks()` and `baseFooterMeta()` respectively — you never need to call those directly.

```php
public function __construct(
    public readonly int $orderId,
    public readonly string $carrier,
    array $links = [],
    array $meta = [],
) {
    parent::__construct($links, $meta);
}
```

---

### Method 1 — `severity(): string`

**Signature:** `public function severity(): string`

**Returns one of:** `error` | `warning` | `info` | `success` | `audit` | `deployment`

**When to override:** Always — this sets the embed color, the default channel, and the severity key in the footer contract.

**Example:**

```php
public function severity(): string
{
    return 'error';
}
```

**Important:** If you call `->error()` on the builder but the event's `severity()` returns `'info'`, Howl throws a `\LogicException` (terminal verb/severity mismatch). Use `->send($event)` to defer to the event's own severity, or call `->severity('error')` on the builder before dispatching. See §5 Common Gotchas.

---

### Method 2 — `title(): string`

**Signature:** `public function title(): string`

**Returns:** A short, emoji-prefixed string. Rendered as the embed title in bold.

**When to override:** Always. Keep it under 80 characters — Discord truncates embed titles.

**Example:**

```php
public function title(): string
{
    return "{$this->emoji()} Order #{$this->orderId} shipped via {$this->carrier}";
}
```

---

### Method 3 — `description(): string`

**Signature:** `public function description(): string`

**Returns:** 1-2 sentence plain-text body. No JSON dumps — save structured data for `fields()`.

**When to override:** Always.

**Example:**

```php
public function description(): string
{
    return "Tracking number: {$this->trackingNumber}. The order has left the warehouse.";
}
```

---

### Method 4 — `fields(): array`

**Signature:** `public function fields(): array`

**Returns:** An array of field dictionaries, each with `name`, `value`, and `inline` keys.

```php
['name' => string, 'value' => string, 'inline' => bool]
```

**When to override:** Always for domain events — this is where you surface IDs, statuses, and metrics.

**Guidelines:**
- Set `'inline' => true` for short values (IDs, status codes). Discord auto-wraps into 2 or 3 columns depending on viewport — never assume a fixed column count.
- Set `'inline' => false` for long values (URLs, messages).
- All values must be strings. Cast integers and floats.

**Example:**

```php
public function fields(): array
{
    return [
        ['name' => '📦 Order', 'value' => (string) $this->orderId, 'inline' => true],
        ['name' => '🚚 Carrier', 'value' => $this->carrier, 'inline' => true],
        ['name' => '🔗 Tracking', 'value' => $this->trackingNumber, 'inline' => true],
    ];
}
```

---

### Method 5 — `footerMeta(): array`

**Signature:** `public function footerMeta(): array`

**Returns:** An associative array of extra footer key-value pairs, merged on top of `baseFooterMeta()`. Your subclass wins on key collision.

`baseFooterMeta()` always provides: `event`, `severity`, `env`, `trace`, and the timestamp. Override only what you need to add.

**When to override:** When your domain requires extra bot-parseable keys in the footer (e.g., `order_id`, `carrier`).

**Naming convention:** Prefix domain keys with a short namespace to avoid collision with base keys (`order_id` not `id`, `carrier_code` not `code`).

**Example:**

```php
public function footerMeta(): array
{
    return [
        'order_id' => $this->orderId,
        'carrier'  => $this->carrier,
    ];
}
```

The rendered footer text will include `· order_id:42 · carrier:FedEx` appended to the standard keys.

---

### Method 6 — `emoji(): string`

**Signature:** `public function emoji(): string`

**Returns:** A single Unicode emoji string representing the event class. Used in `title()` and (optionally) field name prefixes.

**When to override:** Always — your event needs its own visual identity.

**Example:**

```php
public function emoji(): string
{
    return '📦';
}
```

Refer to decisions.md §11 for the canonical professional emoji palette.

---

### Method 7 — `channel(): ?string`

**Signature:** `public function channel(): ?string`

**Returns:** An explicit channel routing key (e.g., `'errors'`, `'audit'`), or `null` to let the dispatcher resolve the channel from `severity()`.

**When to override:** When the event must always land in a specific channel regardless of how it is dispatched. Most events return `null` and let severity routing handle it.

**Example:**

```php
// Always route to the 'audit' channel regardless of severity:
public function channel(): ?string
{
    return 'audit';
}

// Defer to severity-based routing (most common):
public function channel(): ?string
{
    return null;
}
```

---

### Method 8 — `codeBlocks(): array`

**Signature:** `public function codeBlocks(): array`

**Default:** `return [];` — override only when the event should emit fenced code blocks.

**Returns:** An array of code-block dictionaries, each with `name`, `code`, and `lang` keys:

```php
['name' => '📁 Source', 'code' => '...', 'lang' => 'php']
```

**When to override:** Events that benefit from structured, fenced output separate from inline fields — for example:

- Exception traces or file:line references (`lang: 'php'`)
- JSON payloads or diffs (`lang: 'json'`)
- Config snippets, SQL queries, or raw text (`lang: 'text'`)

`toPayload()` passes `codeBlocks()` to the `Payload` and the `EmbedBuilder` renders each entry as a Discord embed field with a fenced code block value. Field values that would exceed Discord's 1024-character limit should be truncated before returning.

**Example:**

```php
public function codeBlocks(): array
{
    [$file, $line] = $this->extractCodeContext($this->exception);

    return [
        ['name' => '📁 Source', 'code' => "{$file}:{$line}", 'lang' => 'php'],
    ];
}
```

For diff-style output (before/after state):

```php
public function codeBlocks(): array
{
    if ($this->before === $this->after) {
        return [];
    }

    return [
        [
            'name' => '🔄 Diff',
            'code' => json_encode(
                ['before' => $this->before, 'after' => $this->after],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ),
            'lang' => 'json',
        ],
    ];
}
```

---

### Final helpers (never override)

These three methods are declared `final` on `HowlEvent` — do not attempt to override them in subclasses:

| Method | What it does |
|---|---|
| `renderLinks()` | Converts `$links` constructor arg into Discord `[label](url)` markdown |
| `baseFooterMeta()` | Builds the standard footer key set (event, severity, env, trace, timestamp) |
| `toPayload()` | Assembles the complete `Payload` object from all 8 contract methods |

`toPayload()` is the orchestrator — it calls all 8 contract methods, merges `footerMeta()` on top of `baseFooterMeta()`, calls `renderLinks()`, and returns the final `Payload`. The driver calls this once; you never call it manually.

---

## 3. The `links([...])` Convention

The `$links` constructor parameter is the standard way to attach inline hyperlinks to the rendered embed. It is consumed by `renderLinks()` automatically — you do not need to call `renderLinks()` yourself.

### Basic form — keyed array

Pass an associative array where keys become the link label:

```php
Howl::onDiscord()->send(new OrderShippedEvent(
    orderId: 42,
    carrier: 'FedEx',
    links: [
        'order'    => 'https://shop.example.com/admin/orders/42',
        'tracking' => 'https://www.fedex.com/track/TRK-987654',
    ],
));
```

`renderLinks()` converts each entry to `[order](url) · [tracking](url)` using Discord's `[label](url)` inline link syntax (live-verified — Discord renders inline hyperlinks inside embed field values).

### Override form — `['text' => ..., 'url' => ...]`

When the key is not a good label (e.g., it's a database ID), use the explicit form:

```php
links: [
    ['text' => 'View Order #42', 'url' => 'https://shop.example.com/admin/orders/42'],
    ['text' => 'Track Package',  'url' => 'https://www.fedex.com/track/TRK-987654'],
],
```

### Key-to-emoji mapping

`renderLinks()` automatically prefixes well-known keys with an emoji:

| Key | Prefix emoji |
|---|---|
| `order` | 📦 |
| `tracking` | 🔗 |
| `admin` | 🛠️ |
| `logs` | 📋 |
| `deploy` | 🚀 |

Unknown keys render without an emoji prefix. You can always use the explicit `['text'=>, 'url'=>]` form to fully control the label.

### Where links appear

`renderLinks()` appends the rendered link list as a non-inline field at the bottom of `fields()`. This placement keeps links visually separated from your domain fields.

---

## 4. The `footerMeta` Extension Pattern

The footer pipe-delimited string is the **bot-parseable contract** for downstream automations (GitHub issue bots, PagerDuty bridges, etc.). Subclasses extend it safely via `footerMeta()`.

### Merge semantics

`toPayload()` merges in this order:

```
baseFooterMeta()  ← package-provided keys (event, severity, env, trace, timestamp)
  +
footerMeta()      ← your subclass keys
```

**Your keys win on collision.** If your subclass returns `['env' => 'staging']`, it overrides the base `env` key. Use this power deliberately — usually you want to add new keys, not override base ones.

### Recommended app-key naming

- Prefix domain keys: `order_id` not `id`, `carrier_code` not `code`
- Use snake_case consistently (the bot contract is parsed by `split(' · ')` then `split(':')`)
- Keep values short (IDs and codes, not long strings)
- Avoid colons and middot characters in values — they are field/entry delimiters

### Example footer output

For `OrderShippedEvent` with `footerMeta()` returning `['order_id' => 42, 'carrier' => 'FedEx']`:

```
event:order.shipped · severity:info · env:production · trace:01HXY3K... · order_id:42 · carrier:FedEx · 11/05/2026 14:30
```

---

## 5. Common Gotchas

### Severity-mismatch `\LogicException`

Howl throws `\LogicException` when the terminal verb conflicts with the event's declared severity:

```php
// WRONG — ->error() conflicts with severity() returning 'info'
Howl::onDiscord()->error(new OrderShippedEvent(...));
// throws \LogicException: "Cannot dispatch OrderShippedEvent (severity: info) via ->error()"
```

**Fix option A — defer to event severity (recommended):**

```php
Howl::onDiscord()->send(new OrderShippedEvent(...));
// uses severity() = 'info' automatically
```

**Fix option B — explicit severity override on builder:**

```php
Howl::onDiscord()->severity('error')->send(new OrderShippedEvent(...));
// forces error severity, overriding event's own severity()
```

Use option B only when the dispatch context (e.g., inside a catch block) requires escalating severity beyond what the event declares.

---

### Null webhook targets

If `Howl::onDiscord()` is called but `HOWL_DISCORD_DEFAULT` is not set in `.env`, Howl logs a warning and returns silently — it never throws. This means a misconfigured environment produces silent failures. Add a smoke test to your feature branch that asserts `Howl::fake()` receives the event.

---

### Sanitizing exception traces

Never pass raw `$exception->getTraceAsString()` into a field value. Discord embed field values have a 1024-character limit; a raw trace will be silently truncated or rejected.

Instead, extract only what you need:

```php
public function fields(): array
{
    return [
        [
            'name'   => '📁 Source',
            'value'  => sprintf('`%s:%d`', $this->exception->getFile(), $this->exception->getLine()),
            'inline' => false,
        ],
    ];
}
```

For the full sanitized trace (first N frames), use `GenericExceptionEvent` which handles truncation internally, or apply your own `mb_substr($trace, 0, 900)` before assigning.

---

### Async-safe constructors (queued events)

If you dispatch events via a queued job, **do not pass Eloquent models** into the constructor — the model may not survive serialization if the queue worker runs on a different machine or after the request lifecycle ends.

**Wrong (queued context):**

```php
new OrderShippedEvent(order: $order, carrier: $carrier)
// $order is a full Eloquent model — may fail to unserialize
```

**Correct:**

```php
new OrderShippedEvent(orderId: $order->id, carrier: $carrier->code)
// re-hydrate inside the event or job handler if needed
```

Pass IDs and scalar values; re-hydrate inside the handler if you need the full model.

---

### Forgetting `parent::__construct($links, $meta)`

If your constructor does not call `parent::__construct($links, $meta)`, the `$links` and `$meta` properties on `HowlEvent` are never set. `renderLinks()` and `baseFooterMeta()` will produce empty output silently. Always chain to the parent constructor.

---

## 6. Testing Your Template

Howl provides `Howl::fake()` for test assertions without hitting Discord. Pair it with Pest for a clean fixture-based test.

### Basic structure

```php
use Skaisser\Howl\Facades\Howl;
use App\Howl\Events\OrderShippedEvent;

it('sends OrderShippedEvent with correct fields', function () {
    Howl::fake();

    $event = new OrderShippedEvent(
        orderId: 42,
        trackingNumber: 'TRK-987654',
        carrier: 'FedEx',
        links: [
            'order'    => 'https://shop.example.com/admin/orders/42',
            'tracking' => 'https://www.fedex.com/track/TRK-987654',
        ],
    );

    Howl::onDiscord()->send($event);

    Howl::assertSentEvent('order_shipped');
});
```

### Assert on the payload shape

```php
it('OrderShippedEvent payload has correct severity and fields', function () {
    Howl::fake();

    Howl::onDiscord()->send(new OrderShippedEvent(42, 'TRK-987654', 'FedEx'));

    Howl::assertSent(function ($payload) {
        return $payload->severity === 'info'
            && str_contains($payload->title, 'Order #42')
            && collect($payload->fields)->contains('name', '🚚 Carrier');
    });
});
```

### Assert footer meta keys

```php
it('OrderShippedEvent footer includes order_id', function () {
    Howl::fake();

    Howl::onDiscord()->send(new OrderShippedEvent(42, 'TRK-987654', 'FedEx'));

    Howl::assertSent(function ($payload) {
        return str_contains($payload->footer['text'], 'order_id:42');
    });
});
```

### What to assert

For every new template, at minimum assert:

1. `severity` matches the declared value
2. `title` contains the primary domain identifier
3. `fields` contains the expected field names
4. `footerMeta` keys appear in the footer text
5. `channel` returns the expected value (or `null`)

Do not assert on the raw Discord HTTP payload structure — assert on the `Payload` shape. The driver tests cover HTTP serialization separately.

---

## Further Reading

- [`docs/example-app-template.md`](example-app-template.md) — full `OrderShippedEvent` worked example with Pest test + dispatch site
- [`CHANGELOG.md`](../CHANGELOG.md) — release history and migration notes
