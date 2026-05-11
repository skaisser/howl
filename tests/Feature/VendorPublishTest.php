<?php

use Illuminate\Support\Facades\Artisan;

it('vendor:publish --tag=howl-config copies config/howl.php to the app config path', function () {
    // Determine the config path for the testbench app
    $configPath = $this->app->configPath();

    // Ensure we are working with a clean slate
    $destination = $configPath.'/howl.php';

    if (file_exists($destination)) {
        unlink($destination);
    }

    $exitCode = Artisan::call('vendor:publish', [
        '--tag' => 'howl-config',
        '--force' => true,
    ]);

    // Artisan::call returns 0 on success
    expect($exitCode)->toBe(0)
        ->and(file_exists($destination))->toBeTrue();

    $config = require $destination;

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('driver')
        ->and($config)->toHaveKey('skip_environments');

    // Clean up
    @unlink($destination);
});
