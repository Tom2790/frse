<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NoteController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

// Publiczne. Throttle 6/min chroni logowanie przed zgadywaniem hasel, a rejestracje
// przed masowym zakladaniem kont.
Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:6,1')
    ->name('api.register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:6,1')
    ->name('api.login');

// Chronione. Sanctum przyjmuje tu token osobisty albo sesje SPA.
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/user', [AuthController::class, 'me'])->name('api.user');

    // apiResource daje GET, POST, GET/{id}, PUT|PATCH/{id}, DELETE/{id}.
    Route::apiResource('notes', NoteController::class)
        ->names('api.notes')
        ->whereNumber('note');

    // read-all musi byc przed trasa z parametrem, inaczej "read-all" zostanie
    // dopasowane jako {notification}.
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('api.notifications.read-all');

    Route::get('/notifications', [NotificationController::class, 'index'])
        ->name('api.notifications.index');

    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->whereNumber('notification')
        ->name('api.notifications.read');
});
