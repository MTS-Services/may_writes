<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->preventRequestForgery(except: [
            'webhook/stripe',
            'webhook/trello',
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ApiErrorException $exception, Request $request) {
            Log::error('Stripe API error', ['message' => $exception->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Payment provider is unavailable. Please try again.'], 422);
            }

            return null;
        });

        $exceptions->render(function (RuntimeException $exception, Request $request) {
            if (! str_contains($exception->getMessage(), 'Trello')) {
                return null;
            }

            Log::error('Trello service error', ['message' => $exception->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Trello integration is temporarily unavailable.'], 422);
            }

            return null;
        });
    })->create();
