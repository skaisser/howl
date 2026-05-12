<?php

use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Skaisser\Howl\Jobs\SendHowlJob;
use Skaisser\Howl\Support\Payload;

function rateLimitPayload(): Payload
{
    return new Payload(
        title: 'Rate Limit Test',
        description: null,
        severity: 'info',
        channel: null,
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: null,
        env: null,
        timestamp: null,
        forceSync: false,
    );
}

// ---------------------------------------------------------------------------
// Phase 5 — Opt-in queue rate-limit middleware
// ---------------------------------------------------------------------------

it('middleware() returns empty array when rate_limiter_key is null (default)', function () {
    config(['howl.rate_limiter_key' => null]);

    $job = new SendHowlJob(rateLimitPayload(), 'discord');

    expect($job->middleware())->toBe([]);
});

it('middleware() returns a RateLimitedWithRedis instance when rate_limiter_key is set', function () {
    config(['howl.rate_limiter_key' => 'howl-test']);

    $job = new SendHowlJob(rateLimitPayload(), 'discord');
    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(RateLimitedWithRedis::class);
});

it('tries and backoff are unchanged (3 tries, [1, 4, 16] backoff)', function () {
    $job = new SendHowlJob(rateLimitPayload(), 'discord');

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([1, 4, 16]);
});
