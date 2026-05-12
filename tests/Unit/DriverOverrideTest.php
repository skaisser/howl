<?php

use Skaisser\Howl\Events\GenericExceptionEvent;
use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Support\Payload;

// ---------------------------------------------------------------------------
// Phase 2 — Per-call driver override plumbing
// ---------------------------------------------------------------------------

it('Howl::driver("null")->error($event) resolves to the null driver', function () {
    $fake = Howl::fake();

    // The null driver always returns true — verify driver field is plumbed through
    Howl::driver('null')->error('Test from null driver');

    expect($fake->sent())->toHaveCount(1)
        ->and($fake->sent()[0]->driver)->toBe('null');
});

it('Howl::error($event) without driver override resolves to configured driver (no driver in payload)', function () {
    $fake = Howl::fake();

    Howl::error('Test with default driver');

    expect($fake->sent())->toHaveCount(1)
        ->and($fake->sent()[0]->driver)->toBeNull();
});

it('per-call driver override does NOT mutate the singleton driver field', function () {
    // Verify singleton identity stays stable before/after a per-call override
    $howl = app('howl');
    $driverBefore = $howl->getDriver();

    // Issue a notification with a driver override
    Howl::fake();
    Howl::driver('null')->error('Test');

    // Re-resolve the singleton; its internal driver should be unchanged
    $driverAfter = app('howl')->getDriver();

    expect($driverBefore)->toBeObject()
        ->and($driverAfter)->toBeObject()
        ->and(get_class($driverBefore))->toBe(get_class($driverAfter));
});

it('Howl::driver("slack")->error($event) throws InvalidArgumentException (driver name flows through)', function () {
    // Set up non-test environment so dispatch actually runs (not skip_environments short-circuit)
    $this->app['config']->set('howl.app_env', 'production');
    $this->app['config']->set('howl.skip_environments', ['testing']);
    $this->app['config']->set('howl.driver', 'discord');

    $this->app->forgetInstance('howl');
    $this->app->singleton('howl', fn ($app) => new HowlClass($app['config']->get('howl', [])));

    // Directly dispatch a Payload with driver='slack' to bypass skip_environments
    $howl = $this->app->make('howl');

    $payload = new Payload(
        title: 'Test',
        description: null,
        severity: 'error',
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
        driver: 'slack',
    );

    // The dispatch should return false (exception logged) because 'slack' is unknown
    // The resolveDriver() call inside dispatchToDriverOnChannel throws InvalidArgumentException
    // which is caught and logged, causing dispatch to return false
    $result = $howl->dispatch($payload);

    expect($result)->toBeFalse();
});
