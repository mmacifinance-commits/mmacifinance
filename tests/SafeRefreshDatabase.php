<?php

namespace Tests;

trait SafeRefreshDatabase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected function migrateDatabases()
    {
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Refusing to initialize a persistent database.');
        }
        $this->artisan('migrate', ['--force' => true]);
    }
}
