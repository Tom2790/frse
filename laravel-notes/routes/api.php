<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trasy publiczne — uwierzytelnianie (Zadanie 1)
|--------------------------------------------------------------------------
| Limit żądań chroni logowanie przed zgadywaniem haseł (6 prób na minutę).
| Rejestracja ma ten sam limit, żeby nie dało się masowo zakładać kont.
*/
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1')
    ->name('api.register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('api.login');

/*
|--------------------------------------------------------------------------
| Trasy chronione (Sanctum: token osobisty ALBO sesja SPA)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    // Zadanie 1: pełny CRUD. `apiResource` daje GET, POST, GET/{id}, PUT|PATCH/{id}, DELETE/{id}.
    Route::apiResource('notes', NoteController::class)
        ->names('api.notes')
        ->whereNumber('note');

    // Zadanie 5a: powiadomienia dla komponentu dzwonka.
    // `read-all` MUSI być przed trasą z parametrem — inaczej „read-all” zostałoby
    // dopasowane jako {notification}.
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('api.notifications.read-all');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('api.notifications.index');

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereNumber('notification')
        ->name('api.notifications.read');
});
