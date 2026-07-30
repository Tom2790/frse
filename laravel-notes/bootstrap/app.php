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
        // Sanctum w trybie SPA: żądania z własnego frontendu (widget Vue w Bladzie)
        // uwierzytelniają się ciasteczkiem sesji, nie tokenem w nagłówku.
        // Bez tego axios z `withCredentials` dostawałby 401 na /api/notes.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Brak zasobu (także „cudza notatka”) → 404 z neutralnym komunikatem.
        // Domyślny komunikat Laravela zdradzałby nazwę klasy modelu i ID.
        //
        // Callback rejestrujemy na `NotFoundHttpException`, a nie `ModelNotFoundException`:
        // handler Laravela najpierw mapuje wyjątek (`prepareException()`), a dopiero potem
        // sprawdza własne callbacki — do tego momentu ModelNotFoundException już nie istnieje.
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Nie znaleziono zasobu.'], 404);
            }

            return null;
        });
    })->create();
