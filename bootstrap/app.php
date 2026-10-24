<?php

use App\Http\Middleware\AdminAuthMiddleware;
use App\Http\Middleware\ApiKeyMiddleware;
use App\Http\Middleware\AssignRequestId;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->alias([
            'admin' => AdminAuthMiddleware::class,
            'api_key' => ApiKeyMiddleware::class,
        ]);
        $middleware->preventRequestForgery(except: [
            'offer',
            'api/get-stats',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->dontReportDuplicates();
        $exceptions->context(function (Throwable $e): array {
            $context = [
                'event_name' => 'exception.reported',
            ];

            if ($e instanceof QueryException) {
                $info = $e->errorInfo ?? null;
                if (is_array($info)) {
                    if (isset($info[0]) && is_string($info[0]) && $info[0] !== '') {
                        $context['sqlstate'] = $info[0];
                    }
                    if (isset($info[1]) && $info[1] !== null && $info[1] !== '') {
                        $context['driver_code'] = (string) $info[1];
                    }
                }
            }

            return $context;
        });
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            $requestId = $request->attributes->get('request_id');
            if (is_string($requestId) && $requestId !== '') {
                $response->headers->set('X-Request-Id', $requestId);
            }

            return $response;
        });
    })
    ->withEvents(false)
    ->create();
