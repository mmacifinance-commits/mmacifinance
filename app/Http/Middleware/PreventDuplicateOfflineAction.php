<?php

namespace App\Http\Middleware;

use App\Models\AuditTrail;
use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateOfflineAction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->header('X-Offline-Sync') || $request->isMethodSafe()) return $next($request);
        abort_unless($request->user(), 401);
        $key = $request->header('X-Offline-Action-Id');
        abort_unless(is_string($key) && preg_match('/^[a-f0-9-]{36}$/i', $key), 428, 'Refresh the app before syncing offline changes.');

        return DB::transaction(function () use ($request, $next, $key) {
            // Serializes requests from the same user; the result and changes commit together.
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $fingerprint = hash('sha256', json_encode([$request->method(), $request->path(), $request->all(), $user->role], JSON_THROW_ON_ERROR));
            $previous = AuditTrail::where('user_id', $user->id)->where('action', 'offline_synced')
                ->where('remarks', $key)->first();
            if ($previous) {
                abort_unless(hash_equals($previous->metadata['fingerprint'], $fingerprint), 409);
                return response($previous->metadata['body'], $previous->metadata['status'], $previous->metadata['headers']);
            }

            $response = $next($request);
            $failedRedirect = $response->isRedirection() && (!$request->hasSession() || !$request->session()->has('success') || $request->session()->has('error') || $request->session()->has('errors'));
            if ($failedRedirect) {
                throw new HttpResponseException(response()->json(['message' => 'The offline change was not saved. Review the record online before retrying.'], 422));
            }
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }
            AuditTrail::log($user, 'offline_synced', $user, $key, [
                'fingerprint' => $fingerprint,
                'body' => $response->getContent(),
                'status' => $response->getStatusCode(),
                'headers' => array_intersect_key($response->headers->all(), array_flip(['content-type', 'location'])),
            ]);
            return $response;
        });
    }
}
