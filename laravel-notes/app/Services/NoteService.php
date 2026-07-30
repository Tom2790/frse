<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\NoteCreated;
use App\Exceptions\NoteLimitExceededException;
use App\Models\Note;
use App\Models\User;
use App\Repositories\Contracts\NoteRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Reguly biznesowe notatek. Serwis nie zna HTTP, repozytorium nie zna regul.
 */
final class NoteService
{
    /** Ile notatek moze miec jeden uzytkownik. */
    public const int MAX_NOTES_PER_USER = 100;

    public const int DEFAULT_PER_PAGE = 15;

    /** Gorna granica per_page, zeby klient nie poprosil o 100 000 rekordow naraz. */
    public const int MAX_PER_PAGE = 50;

    public function __construct(
        private readonly NoteRepositoryInterface $notes,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Note>
     */
    public function paginate(User $user, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage = min(max($perPage ?? self::DEFAULT_PER_PAGE, 1), self::MAX_PER_PAGE);

        return $this->notes->all($user, $perPage);
    }

    public function count(User $user): int
    {
        return $this->notes->countForUser($user);
    }

    public function countPinned(User $user): int
    {
        return $this->notes->countPinnedForUser($user);
    }

    /**
     * Cudza notatka konczy sie tu tak samo jak nieistniejaca, czyli 404.
     * Nie potwierdzamy istnienia zasobow, ktorych uzytkownik nie powinien widziec.
     *
     * @throws ModelNotFoundException
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
     * @param  array{title: string, content: string, is_pinned?: bool}  $data
     *
     * @throws NoteLimitExceededException
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

        event(new NoteCreated($note));

        return $note;
    }

    /**
     * Obsluguje tez aktualizacje czesciowa, np. samo przelaczenie is_pinned z widgetu.
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

    /** Ile notatek uzytkownik moze jeszcze dodac. */
    public function remainingQuota(User $user): int
    {
        return max(self::MAX_NOTES_PER_USER - $this->notes->countForUser($user), 0);
    }
}
