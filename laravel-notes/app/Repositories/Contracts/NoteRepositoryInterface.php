<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Kontrakt dostępu do notatek.
 *
 * Każda metoda przyjmuje `User` — właściciel jest częścią kontraktu, nie opcją.
 * Dzięki temu izolacji danych nie da się „zapomnieć” na poziomie wywołania:
 * warstwa wyżej nie ma w ogóle metody, która zwróciłaby notatki wszystkich.
 */
interface NoteRepositoryInterface
{
    /**
     * Notatki użytkownika, stronicowane (przypięte najpierw).
     *
     * @return LengthAwarePaginator<int, Note>
     */
    public function all(User $user, int $perPage = 15): LengthAwarePaginator;

    /**
     * Notatka użytkownika lub null, gdy nie istnieje albo należy do kogoś innego.
     */
    public function find(int $id, User $user): ?Note;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Note;

    /**
     * @param  array<string, mixed>  $data
     *
     * @return Note|null Zaktualizowana notatka lub null, gdy nie należy do użytkownika.
     */
    public function update(int $id, array $data, User $user): ?Note;

    /**
     * @return bool Czy notatka została usunięta (false = nie istnieje / nie należy do użytkownika).
     */
    public function delete(int $id, User $user): bool;

    /**
     * Liczba wszystkich notatek użytkownika (potrzebna m.in. do limitu i liczników UI).
     */
    public function countForUser(User $user): int;

    /**
     * Liczba przypiętych notatek użytkownika — licznik globalny, nie tylko bieżąca strona.
     */
    public function countPinnedForUser(User $user): int;
}
