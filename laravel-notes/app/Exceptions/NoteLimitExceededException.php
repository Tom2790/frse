<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Wyjatek sam renderuje sie do odpowiedzi HTTP, wiec kontroler nie musi go lapac.
 * 422, a nie 403, bo to nie kwestia uprawnien - po prostu nie ma juz miejsca.
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
        // Bez klucza errors, bo to nie blad pola - front nie ma go podwieszac pod "Tytul".
        return response()->json(['message' => $this->getMessage()], 422);
    }
}
