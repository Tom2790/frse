<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Note;
use App\Models\User;
use App\Repositories\Contracts\NoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

/**
 * Atrapa repozytorium trzymająca notatki w pamięci.
 *
 * Jej istnienie jest najlepszym dowodem, że warstwa z Zadania 2 ma sens:
 * `NoteService` da się przetestować bez bazy danych, migracji i Eloquenta —
 * bo zna wyłącznie interfejs `NoteRepositoryInterface`.
 */
final class InMemoryNoteRepository implements NoteRepositoryInterface
{
    /** @var array<int, Note> */
    private array $notes = [];

    private int $nextId = 1;

    /** Liczniki wywołań — pozwalają sprawdzić, że serwis nie robi zbędnej pracy. */
    public int $createCalls = 0;

    /**
     * @return LengthAwarePaginator<int, Note>
     */
    public function all(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $owned = $this->ownedBy($user)->values();

        return new Paginator(
            items: $owned->take($perPage),
            total: $owned->count(),
            perPage: $perPage,
            currentPage: 1,
        );
    }

    public function find(int $id, User $user): ?Note
    {
        $note = $this->notes[$id] ?? null;

        return $note !== null && $note->user_id === $user->id ? $note : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Note
    {
        $this->createCalls++;

        $note = new Note($data);
        $note->id = $this->nextId++;
        $note->user_id = $user->id;
        $note->is_pinned = (bool) ($data['is_pinned'] ?? false);

        $this->notes[$note->id] = $note;

        return $note;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data, User $user): ?Note
    {
        $note = $this->find($id, $user);

        if ($note === null) {
            return null;
        }

        $note->fill($data);

        return $note;
    }

    public function delete(int $id, User $user): bool
    {
        if ($this->find($id, $user) === null) {
            return false;
        }

        unset($this->notes[$id]);

        return true;
    }

    public function countForUser(User $user): int
    {
        return $this->ownedBy($user)->count();
    }

    public function countPinnedForUser(User $user): int
    {
        return $this->ownedBy($user)->filter(fn (Note $note): bool => $note->is_pinned)->count();
    }

    /**
     * Wypełnia atrapę N notatkami danego użytkownika (przygotowanie testu limitu).
     */
    public function seed(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $this->create(['title' => "Notatka {$i}", 'content' => 'Treść'], $user);
        }

        $this->createCalls = 0;
    }

    /**
     * @return Collection<int, Note>
     */
    private function ownedBy(User $user): Collection
    {
        return (new Collection($this->notes))
            ->filter(fn (Note $note): bool => $note->user_id === $user->id);
    }
}
