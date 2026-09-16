<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        $middleware->append(\App\Http\Middleware\RequestLogContext::class);

        $middleware->web(append: [
            \App\Http\Middleware\PreventDuplicateOfflineAction::class,
            \App\Http\Middleware\EnsureOfflineVersion::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $exception, $request) {
            $status = $response->getStatusCode();
            // Keep Laravel's form validation and authentication redirects intact.
            if (!in_array($status, [401, 403, 404, 405, 409, 413, 419, 429, 500, 502, 503, 504])) {
                return $response;
            }

            $message = match ($status) {
                401, 419 => 'Your session has expired. Refresh the page and sign in again.',
                403 => 'Your account does not have permission to perform this action.',
                404 => 'The requested page or record could not be found.',
                409 => 'This record has changed. Refresh and review the latest version.',
                413 => 'The uploaded file is too large. Please choose a smaller file.',
                429 => 'Too many requests. Please wait a moment before trying again.',
                default => 'We could not complete this request. Please try again shortly. If you were saving, check your records before retrying.',
            };

            $headers = array_intersect_key($response->headers->all(), array_flip(['retry-after', 'allow']));
            $headers['Cache-Control'] = 'no-store';
            if ($request->header('X-Inertia') || $request->expectsJson()) {
                return response()->json(['message' => $message], $status, $headers);
            }

            return response()->view('errors.friendly', compact('status', 'message'), $status, $headers);
        });
    })->create();
