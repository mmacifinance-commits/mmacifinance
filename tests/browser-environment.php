<?php

// Only used by the explicitly started local browser test server, never public/index.php.
$testDatabase = getenv('FINANCE_BROWSER_DATABASE');
if (!$testDatabase || realpath(dirname($testDatabase)) !== realpath(sys_get_temp_dir()) || !preg_match('/^finance-browser-[a-f0-9]+\.sqlite$/', basename($testDatabase))) {
    throw new RuntimeException('Browser tests require a disposable finance-browser database in the system temp directory.');
}
foreach ([
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8137',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('t', 32)),
    'APP_CONFIG_CACHE' => __DIR__.'/nonexistent-config.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $testDatabase, 'DB_URL' => '',
    'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'finance_browser_test', 'SESSION_SECURE_COOKIE' => 'false',
    'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
] as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require_once __DIR__.'/../vendor/autoload.php';
