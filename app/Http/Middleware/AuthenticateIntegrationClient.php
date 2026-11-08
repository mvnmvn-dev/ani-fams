<?php

namespace App\Http\Middleware;

use App\Models\IntegrationClient;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateIntegrationClient
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $token = (string) $request->bearerToken();
        if ($token === '' || ! str_contains($token, '.')) {
            return response()->json(['message' => 'Integration bearer token is required.'], 401);
        }

        [$prefix, $secret] = explode('.', $token, 2);
        $client = IntegrationClient::where('key_prefix', $prefix)
            ->where('is_active', true)
            ->first();

        if (! $client || ! Hash::check($secret, $client->key_hash)) {
            return response()->json(['message' => 'Invalid integration credentials.'], 401);
        }
        if (! in_array($scope, (array) $client->scopes, true)) {
            return response()->json(['message' => 'Integration scope is not permitted.'], 403);
        }

        if ($client->allowed_ips) {
            $allowed = array_values(array_filter(array_map('trim', explode(',', $client->allowed_ips))));
            if ($allowed && ! in_array($request->ip(), $allowed, true)) {
                return response()->json(['message' => 'Client IP is not allowlisted.'], 403);
            }
        }

        if ($client->signing_secret) {
            $timestamp = (int) $request->header('X-FAMS-Timestamp', 0);
            $signature = (string) $request->header('X-FAMS-Signature', '');
            if (! $timestamp || abs(now()->timestamp - $timestamp) > 300 || $signature === '') {
                return response()->json(['message' => 'Invalid or expired integration signature.'], 401);
            }
            $expected = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $client->signing_secret);
            if (! hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid integration signature.'], 401);
            }
        }

        $client->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('integration_client', $client);

        // Domain services use Auth::id() for ownership and audit records. Set
        // the system actor on this request without creating a browser session.
        $systemUser = User::where('email', config('integration.system_user_email'))->first();
        if ($systemUser) {
            Auth::setUser($systemUser);
        }

        return $next($request);
    }
}
