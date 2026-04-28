<?php

use App\Exceptions\PosException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // /api/v1/* uses Sanctum Personal Access Tokens (Bearer) — stateless.
        // We deliberately do NOT call $middleware->statefulApi() here:
        //   - PAT-protected routes don't need session/CSRF.
        //   - statefulApi() rotates the CSRF token on every successful POST,
        //     which broke back-to-back POS checkouts (HTTP 419 on receipt #2).
        //   - The offline-queue replay flow couldn't carry a fresh CSRF token
        //     anyway — it can only carry the bearer.
        // Filament's /admin still has CSRF because the web routing group
        // (routes/web.php) keeps Laravel's default web middleware including
        // VerifyCsrfToken — that path is unchanged.
        // Intentionally empty: web group already has CSRF; api group is
        // bearer-only.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PosException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'data' => null,
                    'meta' => ['error' => $e->getMessage()],
                ], $e->status);
            }

            return null;
        });
    })->create();
