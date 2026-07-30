<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Note;
use App\Models\User;

/**
 * Polityka dostępu do notatek.
 *
 * Rola w architekturze: to DRUGA warstwa izolacji danych. Pierwszą jest repozytorium,
 * które każde zapytanie zawęża do właściciela (`Note::ownedBy()`), więc cudza notatka
 * kończy się jako 404 i polityka nigdy nie zobaczy obcego modelu. Polityka pilnuje
 * reguły „właściciel i tylko właściciel” tam, gdzie model już jest wczytany — i wyłapie
 * błąd, gdyby ktoś kiedyś dopisał niezawężone zapytanie.
 */
class NotePolicy
{
    /**
     * Dostęp do listy — każdy uwierzytelniony użytkownik widzi swoją listę.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Note $note): bool
    {
        return $this->owns($user, $note);
    }

    /**
     * Tworzenie: reguła ilościowa (limit) należy do serwisu i zwraca 422.
     * Polityka odpowiada tylko na pytanie „czy ten użytkownik w ogóle może tworzyć notatki”.
     */
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
