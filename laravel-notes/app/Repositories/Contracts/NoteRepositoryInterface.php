<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Dostep do notatek.
 *
 * Kazda metoda wymaga Usera, bo wlasciciel jest czescia kontraktu, a nie opcjonalnym
 * filtrem. Nie ma tu metody zwracajacej notatki wszystkich, wiec o izolacji danych
 * nie da sie zapomniec w miejscu wywolania.
 */
interface NoteRepositoryInterface
{
    /**
     * Stronicowane notatki uzytkownika, przypiete na gorze.
     *
     * @return LengthAwarePaginator<int, Note>
     */
    public function all(User $user, int $perPage = 15): LengthAwarePaginator;

    /** Notatka uzytkownika albo null, gdy nie istnieje lub nalezy do kogos innego. */
    public function find(int $id, User $user): ?Note;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): Note;

    /**
     * @param  array<string, mixed>  $data
     * @return Note|null null, gdy notatka nie nalezy do uzytkownika
     */
    public function update(int $id, array $data, User $user): ?Note;

    /** @return bool false, gdy nie ma czego usunac */
    public function delete(int $id, User $user): bool;

    /** Potrzebne do limitu notatek i do licznikow w UI. */
    public function countForUser(User $user): int;

    /** Licznik globalny, nie tylko biezaca strona. */
    public function countPinnedForUser(User $user): int;
}
