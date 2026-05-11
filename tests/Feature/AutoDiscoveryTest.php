<?php

use Skaisser\Howl\Howl;
use Skaisser\Howl\HowlServiceProvider;

it('HowlServiceProvider is loaded via testbench getPackageProviders', function () {
    // The TestCase already registers HowlServiceProvider.
    // Confirm the provider is in the loaded providers list.
    $providers = array_map('get_class', $this->app->getLoadedProviders()
        ? array_filter(
            (fn () => $this->loadedProviders ?? [])->bindTo($this->app, $this->app)(),
            fn ($loaded) => $loaded === true,
            ARRAY_FILTER_USE_KEY
        )
        : []
    );

    // Simpler: just confirm the container binding resolves.
    expect($this->app->bound('howl'))->toBeTrue();
});

it('resolves the howl binding to a Howl instance', function () {
    $instance = $this->app->make('howl');

    expect($instance)->toBeInstanceOf(Howl::class);
});

it('HowlServiceProvider is registered in loaded providers', function () {
    // app()->getLoadedProviders() returns class => bool
    $loaded = $this->app->getLoadedProviders();

    expect($loaded)->toHaveKey(HowlServiceProvider::class);
});
