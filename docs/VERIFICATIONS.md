# Howl — Discord Webhook Capability Verifications

> Source of truth for `DiscordDriver` scope decisions.
> Confirmed-live entries were produced by a sandbox webhook test harness on 2026-05-11.
> Pending entries require a fresh sandbox webhook before scope can be finalised.

---

## Confirmed Live (2026-05-11)

Tested against a sandbox Discord server with one channel and 5 category threads,
using a small embed-payload test harness — 5 embed payloads covering all severity levels and category threads.

| Capability | Verdict | How confirmed |
|---|---|---|
| Thread routing via `?thread_id=N` | ✅ | All 5 category threads received their embed in a single harness run (2026-05-11). Resolution order: per-category `channels` URL → default URL + `threads[category]` thread ID → channel root (no thread set). |
| Username override per message | ✅ | Severity-prefixed `🚨 MyApp · local · errors` displayed correctly per-message, overriding the server-side bot account name. Format driven by `username_format` config (`{severity_emoji} {app} · {env} · {channel}`). |
| HTTP 204 (No Content) as success | ✅ | Discord returns **HTTP 204**, not 200, on successful webhook POST. Confirmed in harness response log. `DiscordDriver` MUST treat 204 as the canonical success status. |
| Color bar via `color` decimal | ✅ | All 6 severity colors rendered as expected: error `15548997` (#ED4245), warning `16765440` (#FFC000), info `3447003` (#4169E1), success `5763719` (#57F287), audit `10181046` (#9B59B6), deployment `1752220` (#1ABC9C). |
| Code blocks in field values | ✅ | ` ```php ` fenced block rendered with syntax highlighting in the `📁 Source` field. Confirmed that `lang` hint is honoured inside embed field values. |
| Footer pipe-delim metadata | ✅ | `event:... · severity:... · env:... · trace:... · 11/05/2026 07:36` rendered as italic dim text, parseable via `text.split(' · ')`. This is the bot-parseable contract defined in Decision #8. |
| Author block (`{severity_emoji} {app} · {env} · {channel}`) | ✅ | Renders above the embed title with the embed color tint, identical to the color bar. Confirmed for all 5 category threads. |
| Inline field auto-wrap (2–3 col) | ✅ | Discord auto-wraps inline fields to 2 **or** 3 columns depending on viewport width — at desktop width the 6-field Info embed rendered as a 3×2 grid. `DiscordDriver` must **not** hardcode column count. |

---

## Still Pending (require sandbox webhook)

The four items below were not tested in the 2026-05-11 sandbox harness run. Each needs a
dedicated sandbox webhook (separate server/channel) so that exploratory payloads do not
pollute production channels. Run these verifications before locking Phase 3 scope.

---

### 1. Link buttons (`components` action row, `type:1` / `type:2 style:5`)

**Question:** Does Discord accept a `components` array containing a `type:1` action row with
a `type:2 style:5` LINK button via a **plain webhook URL** (no `application_id`, no bot
ownership)?

**Why it matters:** If unsupported, `->button()` must be removed from the v1 builder API and
an addendum must be filed against decisions.md §14 (Discord-only v1 features — maximalist).

**Curl recipe:**

```bash
curl -sS -D - -X POST "$HOWL_SANDBOX_WEBHOOK" \
  -H 'Content-Type: application/json' \
  -d '{
    "embeds": [{"title": "Button test", "description": "Testing LINK button via plain webhook URL (no application_id)."}],
    "components": [{
      "type": 1,
      "components": [
        {"type": 2, "style": 5, "label": "Open Link", "url": "https://example.com"}
      ]
    }]
  }'
```

**Expected verdicts:**

| HTTP status | Body | Conclusion |
|---|---|---|
| `204 No Content` | (empty) | LINK buttons **are** supported via plain webhook — keep `->button()` in v1. |
| `400 Bad Request` | `{"message":"…application_id…"}` or similar | Buttons require bot ownership — **drop** `->button()` from v1; file addendum to decisions.md §14. |

---

### 2. Rate-limit header behavior on 429

**Question:** Which headers does Discord return on burst-induced 429, and what values should
`DiscordDriver` parse? Goal: document the parsing strategy for sync mode (warn-and-continue)
vs. queue mode (honor `Retry-After`).

**Curl recipe — burst 3 rapid-fire requests to observe headers:**

```bash
for i in 1 2 3; do
  echo "--- Request $i ---"
  curl -sS -D - -X POST "$HOWL_SANDBOX_WEBHOOK" \
    -H 'Content-Type: application/json' \
    -d "{\"content\": \"Rate-limit probe $i\"}" \
    -w '\nHTTP %{http_code}\n'
  echo ""
done
```

**For a sustained burst (50 messages), pipe output to a file and grep for 429:**

```bash
for i in $(seq 1 50); do
  curl -sS -D /tmp/rl-headers-$i -X POST "$HOWL_SANDBOX_WEBHOOK" \
    -H 'Content-Type: application/json' \
    -d "{\"content\": \"Burst $i\"}" -o /dev/null &
done
wait
grep -l "HTTP/2 429" /tmp/rl-headers-* | head -5 | xargs cat
```

**Headers to capture:**

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | Requests allowed per window |
| `X-RateLimit-Remaining` | Remaining requests in current window |
| `X-RateLimit-Reset-After` | Seconds until window resets (float) |
| `X-RateLimit-Bucket` | Bucket identifier |
| `Retry-After` | Seconds to wait before retrying (429 only) |

**Expected driver strategy (to be confirmed):**

- **Sync mode:** log a `howl.rate_limited` warning event and continue without retry — caller
  is responsible for backpressure. Do not block the request lifecycle.
- **Queue mode:** catch 429, inspect `Retry-After` header, re-dispatch the queued job with
  `->delay(ceil($retryAfter))`. Max 3 retries (exponential backoff as per Decision #16).

---

### 3. File attachments (multipart)

**Question:** What is the enforced file size cap for webhook multipart POSTs on unboosted vs.
boosted servers, and how does Discord respond on overage?

**Curl recipe — small file (should succeed):**

```bash
echo "Hello from Howl attachment test" > /tmp/howl-test.txt

curl -sS -D - -X POST "$HOWL_SANDBOX_WEBHOOK" \
  -F "payload_json={\"content\":\"Attachment test — small file\"}" \
  -F "files[0]=@/tmp/howl-test.txt;type=text/plain"
```

**Curl recipe — oversized file (to confirm limit enforcement and error shape):**

```bash
# Generate a 26 MB file to exceed the 25 MB unboosted cap
dd if=/dev/urandom of=/tmp/howl-large.bin bs=1M count=26 2>/dev/null

curl -sS -D - -X POST "$HOWL_SANDBOX_WEBHOOK" \
  -F "payload_json={\"content\":\"Attachment test — oversized (should fail)\"}" \
  -F "files[0]=@/tmp/howl-large.bin;type=application/octet-stream"

rm /tmp/howl-large.bin
```

**Reference limits (from Discord docs — verify live):**

| Server boost tier | Max file size |
|---|---|
| Tier 0 (unboosted) | 25 MB |
| Tier 2 | 50 MB |
| Tier 3 | 100 MB |

**Expected verdicts:**

- Small file → `204 No Content`, file appears in channel.
- Oversized file → `413 Request Entity Too Large` or `400 Bad Request` with a body describing
  the limit. Capture the exact error shape for `DiscordDriver` error handling.
- Driver implication: enforce a configurable `max_file_size` (default `25_000_000` bytes) in
  `DiscordDriver::attach()`, throw `HowlAttachmentTooLargeException` before the HTTP call if
  exceeded — do not rely solely on Discord's response.

---

### 4. `<t:UNIX:R>` relative timestamps inside embed field values

**Question:** Do Discord's `<t:UNIX:R>` timestamp tags render as "3m ago" / "just now" when
placed inside `fields[].value`, or only when used in plain `content` text / the embed-level
`timestamp` field?

**Why it matters:** Decision #9 specifies a relative timestamp in the visual design. If field
values do not render timestamp tags, the builder must use the embed-level `timestamp` field
(ISO 8601) as the fallback and document the limitation.

**Curl recipe:**

```bash
TS=$(date +%s)

curl -sS -D - -X POST "$HOWL_SANDBOX_WEBHOOK" \
  -H 'Content-Type: application/json' \
  -d "{
    \"embeds\": [{
      \"title\": \"Timestamp-in-field test\",
      \"description\": \"Testing \<t:${TS}:R\> in field value vs embed timestamp.\",
      \"fields\": [
        {\"name\": \"Relative (field value)\", \"value\": \"<t:${TS}:R>\", \"inline\": true},
        {\"name\": \"Absolute (field value)\", \"value\": \"<t:${TS}:F>\", \"inline\": true}
      ],
      \"timestamp\": \"$(date -u +%Y-%m-%dT%H:%M:%SZ)\"
    }]
  }"
```

**Expected verdicts:**

| Observation | Conclusion |
|---|---|
| Field value renders as "just now" / "3m ago" | `<t:UNIX:R>` **works in field values** — use it in `EmbedBuilder::addRelativeTimestampField()`. |
| Field value renders as raw literal `<t:1700000000:R>` | Timestamp tags **do not render in field values** — use embed-level `timestamp` (ISO 8601) only; remove `addRelativeTimestampField()` from v1 builder API. |

---

## Driver Implementation Implications

These rules apply to `DiscordDriver` regardless of pending verification outcomes.

- **HTTP 204 is canonical success.** `DiscordDriver` MUST treat `204 No Content` as the
  success response for all webhook POSTs. A `200 OK` check alone is wrong and will silently
  misclassify every successful send as a failure.

- **Thread routing resolution order (v1 default):**
  1. Per-category webhook URL if set in `config('howl.drivers.discord.channels.{category}')` — bypasses thread entirely.
  2. Default URL (`config('howl.drivers.discord.webhook_url')`) + per-category `thread_id` from `config('howl.drivers.discord.threads.{category}')` → appended as `?thread_id=N`.
  3. Explicit `->thread($id)` override on the builder — takes precedence over config for ad-hoc use.
  4. If no thread ID resolves, post to the channel root (no query param appended).

- **Inline fields — no hardcoded column count.** Pass all inline fields with `"inline": true`;
  Discord's client auto-wraps based on viewport. Do not inject non-inline spacers to force a
  2-column layout.

- **Pending scope decisions (resolve before Phase 3 kickoff):**
  - Link buttons (`->button()`): keep in v1 only if verification #1 returns 204.
  - File attachments (`->attach()`): add `max_file_size` guard (25 MB default).
  - Field-level relative timestamps: use `<t:UNIX:R>` in field values only if verification #4 confirms rendering; fall back to embed `timestamp` field otherwise.
  - Rate-limit handling: finalize sync vs. queue strategy after capturing live 429 headers.
  - If any pending verification fails, file an addendum to `decisions.md` before writing Phase 3 code.

---

## Reference Harness

A small PHP script that POSTs 5 hand-crafted embed payloads to a sandbox Discord webhook is the **canonical source of working JSON shapes**. When building `DiscordDriver` unit tests and snapshot fixtures, mirror those shapes exactly — do not invent new payloads from Discord's docs alone.

The harness covers:
- One embed per severity category (error, warning, info, success, audit/deployment)
- Thread routing via `?thread_id=N` for each category thread
- Username override pattern: `🚨 MyApp · local · errors` (format: `{severity_emoji} {app} · {env} · {channel}`)
- Inline fields, code block fields, footer pipe-delim metadata, author block, embed color bar
