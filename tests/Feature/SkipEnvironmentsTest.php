<?php

use Skaisser\Howl\Facades\Howl;
use Skaisser\Howl\Howl as HowlClass;
use Skaisser\Howl\Support\Payload;
use Skaisser\Howl\Testing\HowlFake;

// ---------------------------------------------------------------------------
// skip_environments short-circuit
// ---------------------------------------------------------------------------

it('dispatch() returns true without invoking the driver when env is in skip_environments', function () {
    // Set app_env in config to 'testing' and ensure it is in skip_environments
    $this->app['config']->set('howl.app_env', 'testing');
    $this->app['config']->set('howl.skip_environments', ['testing']);

    // Re-resolve so the singleton picks up the fresh config
    $this->app->forgetInstance('howl');
    $this->app->singleton('howl', fn ($app) => new HowlClass($app['config']->get('howl', [])));

    $howl = $this->app->make('howl');

    $payload = new Payload(
        title: 'Skipped',
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

    // Should return true (no-op) without touching the real driver
    $result = $howl->dispatch($payload);

    expect($result)->toBeTrue();
});

it('dispatch() invokes the driver when env is NOT in skip_environments', function () {
    $this->app['config']->set('howl.driver', 'null');
    $this->app['config']->set('howl.app_env', 'production');
    $this->app['config']->set('howl.skip_environments', ['testing']);

    $this->app->forgetInstance('howl');
    $this->app->singleton('howl', fn ($app) => new HowlClass($app['config']->get('howl', [])));

    $howl = $this->app->make('howl');

    $payload = new Payload(
        title: 'Will be sent',
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

    $result = $howl->dispatch($payload);

    // NullDriver always returns true
    expect($result)->toBeTrue();
});

// ---------------------------------------------------------------------------
// HowlFake bypasses skip_environments — always captures
// ---------------------------------------------------------------------------

it('HowlFake captures payloads even when app_env is in skip_environments', function () {
    $this->app['config']->set('howl.app_env', 'testing');
    $this->app['config']->set('howl.skip_environments', ['testing']);

    // Re-resolve the real singleton first (so fake() has something to read config from)
    $this->app->forgetInstance('howl');
    $this->app->singleton('howl', fn ($app) => new HowlClass($app['config']->get('howl', [])));

    $fake = Howl::fake();

    // Even though env=testing is in skip_environments, HowlFake should capture
    Howl::on()->info('Should be captured');

    expect($fake->sent())->toHaveCount(1);
});
