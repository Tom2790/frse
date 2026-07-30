<?php

declare(strict_types=1);

use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Route;

// Widget w Bladzie chodzi na sesyjnym trybie Sanctuma, wiec potrzebuje zwyklego
// logowania sesyjnego. Endpointy /api/* zostaja te same.

Route::redirect('/', '/notes')->name('home');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    // Widok z widgetem.
    Route::view('/notes', 'notes')->name('notes.index');
});
