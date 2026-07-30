<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum w trybie SPA: zadania z naszego frontendu uwierzytelniaja sie
        // ciasteczkiem sesji, nie tokenem. Bez tego axios z withCredentials dostaje 401.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 404 z neutralnym komunikatem, bo domyslny zdradza nazwe klasy modelu i ID.
        //
        // Callback musi byc na NotFoundHttpException, nie na ModelNotFoundException:
        // handler Laravela najpierw mapuje wyjatek w prepareException(), a wlasne
        // callbacki sprawdza pozniej - wtedy ModelNotFoundException juz nie istnieje.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Nie znaleziono zasobu.'], 404);
            }

            return null;
        });
    })->create();
