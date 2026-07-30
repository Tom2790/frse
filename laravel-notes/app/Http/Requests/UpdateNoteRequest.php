<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Aktualizacja notatki.
 *
 * Autoryzacja NIE odbywa się tutaj — właściciela sprawdza polityka w kontrolerze,
 * już na wczytanej notatce (`Gate::authorize('update', $note)`). Request odpowiada
 * wyłącznie za poprawność danych.
 *
 * Pola są opcjonalne (`sometimes`), żeby dało się wysłać aktualizację częściową —
 * widget Vue przełącza samo `is_pinned`, bez odsyłania całej treści notatki.
 */
class UpdateNoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'content' => ['sometimes', 'required', 'string', 'max:20000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Tytuł notatki nie może być pusty.',
            'title.min' => 'Tytuł musi mieć co najmniej :min znaki.',
            'title.max' => 'Tytuł nie może być dłuższy niż :max znaków.',
            'content.required' => 'Treść notatki nie może być pusta.',
            'content.max' => 'Treść nie może być dłuższa niż :max znaków.',
            'is_pinned.boolean' => 'Pole „przypięta” musi być wartością logiczną (true/false).',
        ];
    }

    /**
     * Puste żądanie PUT/PATCH to najpewniej błąd klienta — odrzucamy je z 422
     * zamiast cicho zwracać niezmieniony zasób.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->hasAny(['title', 'content', 'is_pinned'])) {
                    return;
                }

                $validator->errors()->add(
                    'title',
                    'Podaj co najmniej jedno pole do aktualizacji: title, content lub is_pinned.',
                );
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toServicePayload(): array
    {
        return $this->safe()->only(['title', 'content', 'is_pinned']);
    }
}
