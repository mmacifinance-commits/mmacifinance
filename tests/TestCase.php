<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Ignore local/production configuration before any service provider boots.
        foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => __DIR__.'/nonexistent-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '', 'DATABASE_URL' => ''] as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = parent::createApplication();
        if ($app['config']['database.default'] !== 'sqlite' || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new \RuntimeException('Tests are restricted to the in-memory database.');
        }
        return $app;
    }
}
