<?php

use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsurePasswordResetIsEnabled;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\DatabaseConnectionFailure;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            ApplySecurityHeaders::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'password-reset-enabled' => EnsurePasswordResetIsEnabled::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! DatabaseConnectionFailure::matches($exception)) {
                return null;
            }

            $diagnosticId = (string) Str::uuid();
            Log::error('http.database_unavailable', DatabaseConnectionFailure::diagnosticContext($exception) + [
                'diagnostic_id' => $diagnosticId,
                'method' => $request->method(),
                'path' => $request->path(),
            ]);

            $response = $request->expectsJson()
                ? response()->json([
                    'message' => 'El servicio de datos no está disponible temporalmente.',
                    'diagnostic_id' => $diagnosticId,
                ], 503)
                : response()->view('errors.database-unavailable', [
                    'diagnosticId' => $diagnosticId,
                ], 503);

            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Retry-After', '30');
            $response->headers->set('X-Diagnostic-ID', $diagnosticId);
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');

            return $response;
        });
    })->create();
