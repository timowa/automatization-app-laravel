<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Выдаёт request_id на время HTTP-запроса и возвращает его в заголовке ответа.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->requestId($request);
        $request->attributes->set('request_id', $requestId);
        Context::add([
            'request_id' => $requestId,
            'component' => 'http',
            'http_method' => $request->getMethod(),
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        Context::flush();
    }

    private function requestId(Request $request): string
    {
        $incoming = $request->headers->get('X-Request-Id');
        if (is_string($incoming) && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::uuid();
    }
}
