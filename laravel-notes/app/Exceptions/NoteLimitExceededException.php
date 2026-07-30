<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Naruszenie reguły biznesowej: limit notatek na użytkownika.
 *
 * Wyjątek sam wie, jak zamienić się w odpowiedź HTTP (`render()`), więc kontroler
 * nie musi łapać go i tłumaczyć — Laravel zrobi to za nas. Status 422 zamiast 403,
 * bo to problem z treścią żądania (nie ma już miejsca), a nie z uprawnieniami.
 */
final class NoteLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $limit,
    ) {
        parent::__construct("Osiągnięto limit {$limit} notatek. Usuń część notatek, aby dodać nową.");
    }

    public function render(): JsonResponse
    {
        // Tylko `message`, bez klucza `errors` — to nie błąd konkretnego pola,
        // więc front nie powinien podwieszać go pod „Tytuł”.
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
