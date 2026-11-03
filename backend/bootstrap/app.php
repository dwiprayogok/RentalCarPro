<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA: request dari domain stateful (Admin Web) memakai cookie sesi + CSRF.
        $middleware->statefulApi();

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // API murni: jangan redirect ke route "login" yang tidak ada.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('api/*') ? null : '/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Semua pesan error API dalam Bahasa Indonesia dan berformat konsisten: { message, errors? }.
        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json([
                    'message' => 'Data yang dikirim tidak valid.',
                    'errors' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => __('auth.unauthenticated')], 401);
            }
        });

        $exceptions->render(function (AuthorizationException|AccessDeniedHttpException|SpatieUnauthorizedException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => __('auth.forbidden')], 403);
            }
        });

        $exceptions->render(function (ModelNotFoundException|NotFoundHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $message = $e instanceof NotFoundHttpException && ! $e->getPrevious() instanceof ModelNotFoundException
                    ? 'Halaman atau endpoint tidak ditemukan.'
                    : 'Data tidak ditemukan.';

                return response()->json(['message' => $message], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                return response()->json(['message' => 'Metode HTTP tidak diizinkan untuk endpoint ini.'], 405);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isApi) {
            if ($isApi($request)) {
                $seconds = (int) ($e->getHeaders()['Retry-After'] ?? 60);

                return response()->json([
                    'message' => "Terlalu banyak percobaan. Silakan coba lagi dalam {$seconds} detik.",
                    'retry_after' => $seconds,
                ], 429, $e->getHeaders());
            }
        });

        $exceptions->render(function (TokenMismatchException|HttpException $e, Request $request) use ($isApi) {
            if ($isApi($request) && ($e instanceof TokenMismatchException || $e->getStatusCode() === 419)) {
                return response()->json(['message' => 'Sesi keamanan kedaluwarsa. Muat ulang halaman lalu coba lagi.'], 419);
            }
        });
    })->create();
