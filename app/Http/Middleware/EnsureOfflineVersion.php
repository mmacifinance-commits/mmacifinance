<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOfflineVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseVersion = $request->header('X-Offline-Base-Version');
        $isOfflineUpdate = $request->header('X-Offline-Sync')
            && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true);

        if ($isOfflineUpdate && ! $baseVersion) {
            return response()->json([
                'message' => 'Sync stopped because the last-known record version is missing. Refresh online before editing this record offline again.',
            ], 428);
        }

        if (! $baseVersion || ! in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $record = collect($request->route()?->parameters() ?? [])
            ->filter(fn ($parameter) => $parameter instanceof Model && $parameter->usesTimestamps())
            ->last();

        if (! $record || ! $record->updated_at) {
            return $next($request);
        }

        $serverVersion = $record->updated_at->toISOString();
        if (! hash_equals($serverVersion, (string) $baseVersion)) {
            return response()->json([
                'message' => 'Sync conflict: this record changed on the server while you were offline.',
                'base_version' => $baseVersion,
                'server_version' => $serverVersion,
                'current' => $record->fresh(),
            ], 409);
        }

        return $next($request);
    }
}
