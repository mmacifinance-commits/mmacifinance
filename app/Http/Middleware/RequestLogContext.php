<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RequestLogContext
{
    public function handle(Request $request, Closure $next)
    {
        $id = (string) Str::uuid();
        $request->attributes->set('request_id', $id);
        Log::withContext(['request_id' => $id, 'method' => $request->method(), 'path' => $request->path()]);
        try {
            $response = $next($request);
            $response->headers->set('X-Request-Id', $id);
            return $response;
        } finally {
            Log::withoutContext(['request_id', 'method', 'path']);
        }
    }
}
