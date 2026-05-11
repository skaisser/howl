<?php

namespace Skaisser\Howl\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Skaisser\Howl\HowlServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [HowlServiceProvider::class];
    }
}
