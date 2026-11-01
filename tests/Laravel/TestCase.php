<?php

declare(strict_types=1);

namespace ColombiaKit\Tests\Laravel;

use ColombiaKit\Laravel\ColombiaKitServiceProvider;
use Orchestra\Testbench\TestCase as TestbenchTestCase;

abstract class TestCase extends TestbenchTestCase
{
    protected function getPackageProviders($app): array
    {
        return [ColombiaKitServiceProvider::class];
    }
}
