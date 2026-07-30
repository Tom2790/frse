<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Rejestracja, logowanie i wylogowanie w API (Laravel Sanctum, tokeny osobiste).
 *
 * Widget Vue z Zadania 4 korzysta z drugiego trybu Sanctuma — uwierzytelniania
 * sesyjnego (cookie SPA) — obsługiwanego przez trasy web. Oba tryby chronią
 * te same endpointy `/api/*` przez guard `auth:sanctum`.
 */
class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        return response()->json([
            'message' => 'Konto zostało utworzone.',
            'user' => $this->userPayload($user),
            'token' => $user->createToken($request->string('device_name')->toString() ?: 'api')->plainTextToken,
        ], Response::HTTP_CREATED);
    }

    /**
     * @throws ValidationException Gdy dane logowania są nieprawidłowe (422).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        // Jeden komunikat dla „nie ma konta” i „złe hasło” — nie podpowiadamy,
        // które adresy e-mail są zarejestrowane.
        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy e-mail lub hasło.',
            ]);
        }

        return response()->json([
            'message' => 'Zalogowano.',
            'user' => $this->userPayload($user),
            'token' => $user->createToken($request->deviceName())->plainTextToken,
        ]);
    }

    /**
     * Unieważnia token użyty do tego żądania (wylogowanie z jednego urządzenia).
     */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Wylogowano.']);
    }

    /**
     * GET /api/user — dane zalogowanego użytkownika (przydatne do sprawdzenia sesji).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        return response()->json(['user' => $this->userPayload($user)]);
    }

    /**
     * @return array{id: int, name: string, email: string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
