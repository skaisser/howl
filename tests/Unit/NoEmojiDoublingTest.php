<?php

/**
 * Regression: built-in event templates must NOT double the severity emoji
 * in the rendered embed title. EmbedBuilder is the single source of truth for
 * the title-emoji prefix; templates' title() methods return plain text.
 *
 * Asserts substr_count($embed['title'], $event->emoji()) === 1 for every
 * built-in template.
 */

use Skaisser\Howl\Events\AuditEvent;
use Skaisser\Howl\Events\CronHeartbeatEvent;
use Skaisser\Howl\Events\DeploymentEvent;
use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Events\GenericInfoEvent;
use Skaisser\Howl\Events\JobRetryExhaustedEvent;
use Skaisser\Howl\Events\ManualOperationEvent;
use Skaisser\Howl\Support\EmbedBuilder;

dataset('builtin_templates', function () {
    return [
        'GenericExceptionEvent' => fn () => new GenericExceptionEvent(
            new RuntimeException('Payment gateway timeout')
        ),
        'DeploymentEvent' => fn () => new DeploymentEvent(
            version: 'v1.2.0',
            env: 'production',
            commit: 'abc1234',
        ),
        'AuditEvent' => fn () => new AuditEvent(
            actor: 'admin@example.com',
            action: 'role.changed',
            target: 'admin-role',
        ),
        'CronHeartbeatEvent' => fn () => new CronHeartbeatEvent(
            scheduleName: 'invoices:send',
            lastRunAt: new DateTimeImmutable('2026-05-11 08:00:00'),
            status: 'ok',
        ),
        'JobRetryExhaustedEvent' => fn () => new JobRetryExhaustedEvent(
            jobClass: 'App\\Jobs\\ProcessPayment',
            payload: ['order_id' => 42],
            attempts: 3,
            lastException: new RuntimeException('connection refused'),
        ),
        'ManualOperationEvent' => fn () => new ManualOperationEvent(
            operator: 'admin@example.com',
            command: 'orders:reprocess',
        ),
        'GenericInfoEvent' => fn () => new GenericInfoEvent(
            eventTitle: 'Background sync completed',
            eventDescription: 'Synced 1234 rows in 5s.',
        ),
    ];
});

it('renders the severity emoji exactly once in the embed title', function (callable $eventFactory) {
    $event = $eventFactory();
    $payload = $event->toPayload();

    $body = EmbedBuilder::build($payload);
    $title = $body['embeds'][0]['title'];

    // EmbedBuilder prepends the SEVERITY emoji (from config('howl.emojis.<severity>')),
    // which is the emoji that was being doubled in v1.0.0. Verify it appears exactly once.
    $severityEmoji = config('howl.emojis.'.$payload->severity);

    expect(substr_count($title, $severityEmoji))->toBe(
        1,
        "Embed title '{$title}' should contain severity emoji '{$severityEmoji}' exactly once, not doubled."
    );

    // And the template's title() return value MUST NOT start with that severity emoji
    // (templates leave emoji prefixing to EmbedBuilder — the v1.0.0 bug was templates
    // baking their own emoji into title() which then got doubled).
    expect($payload->title)->not->toStartWith($severityEmoji);
})->with('builtin_templates');
