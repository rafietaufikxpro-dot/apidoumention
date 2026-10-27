<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.tracker.api_key');

        if ($expected === '') {
            return response()->json(['message' => 'API key is not configured on the server.'], 503);
        }

        if (! hash_equals($expected, (string) $request->header('X-API-Key'))) {
            return response()->json(['message' => 'Invalid or missing API key.'], 401);
        }

        return $next($request);
    }
}
