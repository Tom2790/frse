<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Trasy web — host dla widgetu Vue (Zadanie 4)
|--------------------------------------------------------------------------
| Widget osadzony w Bladzie korzysta z sesyjnego trybu Sanctuma (cookie SPA),
| dlatego potrzebuje zwykłego logowania sesyjnego. Endpointy /api/* pozostają
| te same — chroni je ten sam guard `auth:sanctum`.
*/

Route::redirect('/', '/notes')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    // Widok z widgetem: <div id="app"><note-manager></note-manager></div>
    Route::view('/notes', 'notes')->name('notes.index');
});
