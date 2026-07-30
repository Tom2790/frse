<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NoteLimitExceededException;
use App\Models\Note;
use App\Models\User;
use App\Repositories\Contracts\NoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Warstwa serwisowa notatek — jedyne miejsce z regułami biznesowymi.
 *
 * Kontrolery mówią „co” (utwórz notatkę dla tego użytkownika), serwis decyduje
 * „na jakich zasadach” (limit, wartości domyślne, zdarzenia domenowe), a repozytorium
 * odpowiada za „gdzie i jak” to zapisać. Serwis nie zna HTTP, a repozytorium — reguł.
 */
final class NoteService
{
    /** Reguła biznesowa: ile notatek może mieć jeden użytkownik. */
    public const int MAX_NOTES_PER_USER = 100;

    /** Domyślny rozmiar strony wymagany w specyfikacji. */
    public const int DEFAULT_PER_PAGE = 15;

    /** Górna granica `per_page` — chroni bazę przed żądaniem 100 000 rekordów. */
    public const int MAX_PER_PAGE = 50;

    public function __construct(
        private readonly NoteRepositoryInterface $notes,
    ) {}

    /**
     * Stronicowana lista notatek użytkownika.
     *
     * @return LengthAwarePaginator<int, Note>
     */
    public function paginate(User $user, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = min(max($perPage ?? self::DEFAULT_PER_PAGE, 1), self::MAX_PER_PAGE);

        return $this->notes->all($user, $perPage);
    }

    /**
     * Globalna liczba notatek użytkownika (licznik dla UI).
     */
    public function count(User $user): int
    {
        return $this->notes->countForUser($user);
    }

    /**
     * Globalna liczba przypiętych notatek użytkownika (licznik dla UI).
     */
    public function countPinned(User $user): int
    {
        return $this->notes->countPinnedForUser($user);
    }

    /**
     * Pojedyncza notatka użytkownika.
     *
     * @throws ModelNotFoundException Gdy notatka nie istnieje albo należy do kogoś innego.
     *                               Świadomie 404, nie 403 — nie potwierdzamy istnienia
     *                               cudzych zasobów.
     */
    public function findOrFail(int $id, User $user): Note
    {
        $note = $this->notes->find($id, $user);

        if ($note === null) {
            throw (new ModelNotFoundException())->setModel(Note::class, [$id]);
        }

        return $note;
    }

    /**
     * Tworzy notatkę z pilnowaniem limitu na użytkownika.
     *
     * @param  array{title: string, content: string, is_pinned?: bool}  $data
     *
     * @throws NoteLimitExceededException Gdy użytkownik wyczerpał limit notatek.
     */
    public function create(array $data, User $user): Note
    {
        if ($this->notes->countForUser($user) >= self::MAX_NOTES_PER_USER) {
            throw new NoteLimitExceededException(self::MAX_NOTES_PER_USER);
        }

        $note = $this->notes->create([
            'title' => $data['title'],
            'content' => $data['content'],
            'is_pinned' => $data['is_pinned'] ?? false,
        ], $user);

        return $note;
    }

    /**
     * Aktualizuje notatkę użytkownika. Obsługuje też aktualizację częściową
     * (np. samo przełączenie `is_pinned` z widgetu Vue).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ModelNotFoundException
     */
    public function update(int $id, array $data, User $user): Note
    {
        $attributes = array_intersect_key($data, array_flip(['title', 'content', 'is_pinned']));

        $note = $this->notes->update($id, $attributes, $user);

        if ($note === null) {
            throw (new ModelNotFoundException())->setModel(Note::class, [$id]);
        }

        return $note;
    }

    /**
     * @throws ModelNotFoundException
     */
    public function delete(int $id, User $user): void
    {
        if (! $this->notes->delete($id, $user)) {
            throw (new ModelNotFoundException())->setModel(Note::class, [$id]);
        }
    }

    /**
     * Ile notatek użytkownik może jeszcze dodać (przydatne w UI i testach).
     */
    public function remainingQuota(User $user): int
    {
        return max(self::MAX_NOTES_PER_USER - $this->notes->countForUser($user), 0);
    }
}
