<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Logowanie sesyjne dla widoku Blade z widgetem Vue (Zadanie 4).
 *
 * Sanctum obsługuje dwa tryby: token osobisty (API, patrz `Api\AuthController`)
 * i sesję cookie dla własnego frontendu. Widget w Bladzie stoi na tym samym
 * origin co API, więc korzysta z sesji — dzięki temu nie trzymamy tokenu
 * w localStorage, a ciasteczko `HttpOnly` jest niedostępne dla JS.
 */
class SessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * @throws ValidationException
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only(['email', 'password']);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy e-mail lub hasło.',
            ]);
        }

        // Nowy identyfikator sesji po zalogowaniu — zabezpieczenie przed session fixation.
        $request->session()->regenerate();

        return redirect()->intended(route('notes.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
