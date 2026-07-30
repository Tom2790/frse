<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Note;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Zdarzenie domenowe: powstała nowa notatka.
 *
 * Publikuje je `NoteService::create()`. Serwis nie wie, co się dalej stanie —
 * dziś to e-mail i powiadomienie w aplikacji, jutro może dojść webhook albo
 * wpis w logu audytowym. Dodanie kolejnego odbiorcy nie wymaga zmiany serwisu.
 *
 * `SerializesModels` sprawia, że do kolejki trafia tylko klucz modelu, a listener
 * odświeża go z bazy — nie wozimy nieaktualnej migawki obiektu przez kolejkę.
 */
class NoteCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Note $note,
    ) {}
}
