<?php

require __DIR__.'/browser-environment.php';
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$asset = realpath(__DIR__.'/../public'.$path);
if ($path !== '/' && $asset && str_starts_with($asset, realpath(__DIR__.'/../public').DIRECTORY_SEPARATOR) && is_file($asset)) return false;
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
\Illuminate\Support\Facades\Route::middleware('web')->get('/__test/login/{role}', function (string $role) {
    \Illuminate\Support\Facades\Auth::login(\App\Models\User::where('role', $role)->firstOrFail());
    request()->session()->regenerate();
    return redirect('/receipts');
});
$response = $kernel->handle($request = \Illuminate\Http\Request::capture());
$response->send();
$kernel->terminate($request, $response);
