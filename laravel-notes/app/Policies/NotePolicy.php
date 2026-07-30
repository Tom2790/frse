<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Druga warstwa izolacji danych. Pierwsza jest repozytorium, ktore zaweza kazde
 * zapytanie do wlasciciela, wiec normalnie polityka nie zobaczy obcej notatki.
 * Zadziala, jesli ktos kiedys dopisze zapytanie bez tego zawezenia.
 */
class NotePolicy
{
    // Kazdy zalogowany widzi wlasna liste.
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    // Limit notatek to regula serwisu (422), nie uprawnienie.
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    private function owns(User $user, Note $note): bool
    {
        return $note->user_id === $user->id;
    }
}
