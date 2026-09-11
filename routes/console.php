<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('test:mysql-setup {--database= : Test database name}', function () {
    $database = $this->option('database') ?: env('DB_TEST_DATABASE', 'budget_fund_utilization_and_tracking_test');
    $connectionName = config('database.default', 'mysql');
    $connection = config("database.connections.{$connectionName}");

    if (($connection['driver'] ?? null) !== 'mysql') {
        $this->error("The {$connectionName} connection is not MySQL.");

        return Command::FAILURE;
    }

    Config::set("database.connections.{$connectionName}.database", null);
    DB::purge($connectionName);

    DB::connection($connectionName)->statement(
        "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    );

    Config::set("database.connections.{$connectionName}.database", $database);
    DB::purge($connectionName);
    DB::connection($connectionName)->getPdo();

    $this->info("MySQL test database ready: {$database}");

    return Command::SUCCESS;
})->purpose('Create the production-like MySQL test database used by phpunit');
