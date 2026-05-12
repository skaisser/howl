<?php

use Illuminate\Support\ServiceProvider;
use Skaisser\Howl\HowlServiceProvider;

it('vendor:publish --tag=howl-config registers config/howl.php as a publishable source', function () {
    // Verify publish REGISTRATION rather than invoking Artisan vendor:publish,
    // which writes to the shared testbench config_path and races with other
    // parallel workers' bootstrap (especially under --coverage where the
    // window widens). The publishes() call inside HowlServiceProvider::boot()
    // is what binds the source-to-destination pair, so asserting on the
    // registration captures the same behaviour without filesystem contention.
    $paths = ServiceProvider::pathsToPublish(
        HowlServiceProvider::class,
        'howl-config'
    );

    expect($paths)->not->toBeEmpty();

    $source = array_key_first($paths);
    $destination = $paths[$source];

    // Source must be the package config file
    expect($source)->toEndWith('/config/howl.php')
        ->and(file_exists($source))->toBeTrue();

    // Destination should be the consumer app's config_path
    expect($destination)->toEndWith('/config/howl.php');

    // The source file must be a valid Laravel config
    $config = require $source;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('driver')
        ->and($config)->toHaveKey('skip_environments');
});
