<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $authorizationHeader = $request->header('Authorization', '');
        $apiKey = config('app.api_key', '');

        if ($apiKey === '' || !$this->verifyApiKey($authorizationHeader, $apiKey)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function verifyApiKey(string $authorizationHeader, string $expectedKey): bool
    {
        if ($expectedKey === '' || $authorizationHeader === '') {
            return false;
        }

        $parts = explode(' ', $authorizationHeader, 2);
        $scheme = strtolower($parts[0] ?? '');
        $token = $parts[1] ?? '';

        return $scheme === 'bearer' && hash_equals($expectedKey, $token);
    }
}
