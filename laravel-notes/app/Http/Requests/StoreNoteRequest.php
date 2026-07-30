<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreNoteRequest extends FormRequest
{
    /**
     * Autoryzacja tworzenia zasobu — polityka `NotePolicy::create()`.
     */
    public function authorize(): bool
    {
        return Gate::allows('create', Note::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Czytelne komunikaty błędów zamiast domyślnych, generycznych.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Tytuł notatki jest wymagany.',
            'title.min' => 'Tytuł musi mieć co najmniej :min znaki.',
            'title.max' => 'Tytuł nie może być dłuższy niż :max znaków.',
            'content.required' => 'Treść notatki jest wymagana.',
            'content.max' => 'Treść nie może być dłuższa niż :max znaków.',
            'is_pinned.boolean' => 'Pole „przypięta” musi być wartością logiczną (true/false).',
        ];
    }

    /**
     * Dane po walidacji, gotowe do przekazania do serwisu.
     *
     * @return array{title: string, content: string, is_pinned?: bool}
     */
    public function toServicePayload(): array
    {
        /** @var array{title: string, content: string, is_pinned?: bool} $payload */
        $payload = $this->safe()->only(['title', 'content', 'is_pinned']);

        return $payload;
    }
}
