<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->api(append: [
            \App\Http\Middleware\FormatJsonResponse::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api') || $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api') || $request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Route atau resource yang diminta tidak ditemukan.',
                    'instance' => $request->fullUrl(),
                ], 404, ['Content-Type' => 'application/problem+json']);
            }
        });

        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api') || $request->is('api/*') || $request->expectsJson()) {
                $invalidParams = [];
                foreach ($e->errors() as $field => $messages) {
                    $invalidParams[] = [
                        'name' => $field,
                        'reason' => $messages[0] ?? 'Nilai tidak valid.',
                    ];
                }

                return response()->json([
                    'type' => 'about:blank',
                    'title' => 'Unprocessable Entity',
                    'status' => 422,
                    'detail' => 'Format payload atau parameter tidak memenuhi validasi.',
                    'instance' => $request->fullUrl(),
                    'invalid_params' => $invalidParams,
                    'invalid-params' => $invalidParams,
                ], 422, ['Content-Type' => 'application/problem+json']);
            }
        });
    })->create();
