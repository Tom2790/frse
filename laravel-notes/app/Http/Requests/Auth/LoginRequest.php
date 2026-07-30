<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Nazwa urządzenia trafia do etykiety tokenu Sanctum — pozwala
            // użytkownikowi odwołać dostęp z konkretnego klienta.
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Adres e-mail jest wymagany.',
            'email.email' => 'Podaj poprawny adres e-mail.',
            'password.required' => 'Hasło jest wymagane.',
        ];
    }

    /**
     * Etykieta tokenu: nazwa urządzenia z żądania, a w razie jej braku — User-Agent.
     */
    public function deviceName(): string
    {
        $deviceName = trim((string) $this->input('device_name', ''));

        if ($deviceName === '') {
            $deviceName = $this->userAgent() ?? 'api';
        }

        return mb_substr($deviceName, 0, 255);
    }
}
