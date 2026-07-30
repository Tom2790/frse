<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Note;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Publikowane przez NoteService::create(). Serwis nie wie, co bedzie dalej - dzis mail,
 * kiedys moze webhook albo log audytowy. Dodanie odbiorcy nie wymaga zmiany serwisu.
 *
 * SerializesModels sprawia, ze do kolejki idzie klucz modelu, a listener odswieza go
 * z bazy. Nie wozimy nieaktualnej migawki obiektu przez kolejke.
 */
class NoteCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Note $note,
    ) {}
}
