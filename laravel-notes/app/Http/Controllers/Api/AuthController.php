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
 * Rejestracja i logowanie na tokenach Sanctuma (dla klientow API).
 *
 * Widget Vue z zadania 4 uzywa drugiego trybu Sanctuma - sesji cookie - obslugiwanego
 * przez trasy web. Oba tryby chronia te same endpointy /api/* guardem auth:sanctum.
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
     * @throws ValidationException
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        // Ten sam komunikat dla "nie ma konta" i "zle haslo", zeby nie dalo sie
        // sprawdzic, ktore adresy sa zarejestrowane.
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

    /** Uniewaznia tylko token uzyty w tym zadaniu, czyli wylogowuje jedno urzadzenie. */
    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Wylogowano.']);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

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
