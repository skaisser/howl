<?php

use Skaisser\Howl\Support\EmbedBuilder;
use Skaisser\Howl\Support\Payload;

/**
 * Severity matrix dataset — parameterized over all 6 severities.
 *
 * Each entry: [severity, expectedColor, expectedEmoji, expectedDefaultChannel]
 *
 * Colors (decimal) and emojis are the canonical values from decisions.md §9.
 * Default channel follows the channel taxonomy from decisions.md §6.
 */
dataset('severity_matrix', [
    'error' => ['error',      15548997, '🚨', 'errors'],
    'warning' => ['warning',    16765440, '🟡', 'warnings'],
    'info' => ['info',        3447003, 'ℹ️', 'info'],
    'success' => ['success',     5763719, '✅', 'success'],
    'audit' => ['audit',      10181046, '🔒', 'audit'],
    'deployment' => ['deployment',  1752220, '🚀', 'deployments'],
]);

function matrixPayload(string $severity, ?string $channel = null): Payload
{
    return new Payload(
        title: 'Test notification',
        description: null,
        severity: $severity,
        channel: $channel,
        fields: [],
        codeBlocks: [],
        mentions: [],
        meta: [],
        buttons: [],
        attachments: [],
        threadId: null,
        username: null,
        app: 'MyApp',
        env: 'production',
        timestamp: new DateTimeImmutable('2026-05-11 06:57:00'),
        forceSync: false,
    );
}

// ---------------------------------------------------------------------------
// Color matrix — EmbedBuilder.build() must return the canonical decimal color
// ---------------------------------------------------------------------------

it('embed color matches the decisions.md canonical value for severity', function (string $severity, int $color) {
    $payload = matrixPayload($severity);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['color'])->toBe($color);
})->with('severity_matrix');

// ---------------------------------------------------------------------------
// Emoji matrix — title must start with the severity emoji prefix
// ---------------------------------------------------------------------------

it('embed title is prefixed with the severity emoji', function (string $severity, int $color, string $emoji) {
    $payload = matrixPayload($severity);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['title'])->toStartWith($emoji);
})->with('severity_matrix');

// ---------------------------------------------------------------------------
// Author block — must contain the severity emoji in the author name
// ---------------------------------------------------------------------------

it('embed author name contains the severity emoji', function (string $severity, int $color, string $emoji) {
    $payload = matrixPayload($severity);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['author']['name'])->toContain($emoji);
})->with('severity_matrix');

// ---------------------------------------------------------------------------
// Footer — must contain severity:<value> for every severity
// ---------------------------------------------------------------------------

it('embed footer contains the severity key-value pair', function (string $severity) {
    $payload = matrixPayload($severity);
    $body = EmbedBuilder::build($payload);

    expect($body['embeds'][0]['footer']['text'])->toContain("severity:{$severity}");
})->with('severity_matrix');

// ---------------------------------------------------------------------------
// Default channel routing — channel field is propagated through the payload
// ---------------------------------------------------------------------------

it('payload channel is passed through correctly for each severity default channel', function (
    string $severity,
    int $color,
    string $emoji,
    string $defaultChannel,
) {
    $payload = matrixPayload($severity, $defaultChannel);
    $body = EmbedBuilder::build($payload);

    // Author name should include the channel name since it's set on the payload
    expect($body['embeds'][0]['author']['name'])->toContain($defaultChannel);
})->with('severity_matrix');
