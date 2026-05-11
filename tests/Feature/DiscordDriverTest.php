<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Skaisser\Howl\Drivers\DiscordDriver;
use Skaisser\Howl\Events\GenericInfoEvent;

// ---------------------------------------------------------------------------
// Shared config stub used across all severity cases.
//
// The six threads entries mirror the severity→channel mapping declared in
// GenericInfoEvent::channel():
//   error      → errors       → thread 1001
//   warning    → warnings     → thread 1002
//   info       → info         → thread 1003
//   success    → info         → thread 1003  (success routes to info thread)
//   audit      → audit        → thread 1004
//   deployment → deployments  → thread 1005
// ---------------------------------------------------------------------------

function discordDriverConfig(): void
{
    config([
        'howl.drivers.discord.webhook_url' => 'https://discord.com/api/webhooks/123/abc',
        'howl.drivers.discord.channels' => [],   // force thread-routing path
        'howl.drivers.discord.threads.errors' => '1001',
        'howl.drivers.discord.threads.warnings' => '1002',
        'howl.drivers.discord.threads.info' => '1003',
        'howl.drivers.discord.threads.audit' => '1004',
        'howl.drivers.discord.threads.deployments' => '1005',
    ]);
}

// ---------------------------------------------------------------------------
// Dataset — [severity, expected thread_id]
// ---------------------------------------------------------------------------

dataset('generic_info_severities', [
    'error' => ['error',      '1001'],
    'warning' => ['warning',    '1002'],
    'info' => ['info',       '1003'],
    'success' => ['success',    '1003'],  // success routes to info thread
    'audit' => ['audit',      '1004'],
    'deployment' => ['deployment', '1005'],
]);

// ---------------------------------------------------------------------------
// Integration test: GenericInfoEvent severity → thread_id in POST URL
//
// Flow:
//   GenericInfoEvent($severity)->channel()
//     → returns the channel string (e.g. 'errors')
//     → toPayload() embeds that channel in the Payload
//     → DiscordDriver::resolveEndpoint() reads threads.<channel> from config
//     → appends ?thread_id=<N> to the POST URL
//
// Http::fake() intercepts the outbound request; Http::assertSent() verifies
// the URL contains the expected thread_id parameter.
// ---------------------------------------------------------------------------

it('routes GenericInfoEvent severity to the correct Discord thread_id', function (string $severity, string $expectedThreadId) {
    discordDriverConfig();

    Http::fake(['*' => Http::response('', 204)]);

    $event = new GenericInfoEvent(
        eventTitle: 'Test notification',
        eventDescription: 'Integration test body',
        eventSeverity: $severity,
    );

    $payload = $event->toPayload();

    $driver = new DiscordDriver;
    $result = $driver->send($payload);

    expect($result)->toBeTrue();

    Http::assertSent(function (Request $req) use ($expectedThreadId) {
        expect($req->url())->toContain('?thread_id='.$expectedThreadId);

        return true;
    });
})->with('generic_info_severities');
