<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Note;
use App\Models\User;
use App\Repositories\Contracts\NoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Jedyne miejsce, ktore wie, ze notatki siedza w bazie i ze uzywamy Eloquenta.
 * Serwis i kontrolery znaja tylko interfejs.
 */
final class EloquentNoteRepository implements NoteRepositoryInterface
{
    /**
     * @return LengthAwarePaginator<int, Note>
     */
    public function all(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Note::query()
            ->ownedBy($user)
            ->pinnedFirst()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id, User $user): ?Note
    {
        return Note::query()
            ->ownedBy($user)
            ->whereKey($id)
            ->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Note
    {
        $note = Note::make($data);
        $note->user()->associate($user);
        $note->save();

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

        $note->fill($data)->save();

        return $note;
    }

    public function delete(int $id, User $user): bool
    {
        $note = $this->find($id, $user);

        if ($note === null) {
            return false;
        }

        return (bool) $note->delete();
    }

    public function countForUser(User $user): int
    {
        return Note::query()->ownedBy($user)->count();
    }

    public function countPinnedForUser(User $user): int
    {
        return Note::query()->ownedBy($user)->where('is_pinned', true)->count();
    }
}
