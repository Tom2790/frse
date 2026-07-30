<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;

/**
 * Logika powiadomień w aplikacji (Zadanie 5a).
 *
 * Dla notatek zbudowaliśmy pełne repozytorium (wymóg Zadania 2). Tutaj model dostępu
 * jest trywialny (jedna tabela, trzy zapytania), więc serwis rozmawia z Eloquentem
 * bezpośrednio — dodanie kolejnego interfejsu byłoby warstwą bez treści. Zasada
 * pozostaje ta sama: kontroler nie dotyka modeli.
 */
final class NotificationService
{
    /** Ile powiadomień pokazuje dzwonek. */
    public const int FEED_LIMIT = 20;

    /**
     * Najnowsze powiadomienia użytkownika.
     *
     * @return Collection<int, Notification>
     */
    public function latestFor(User $user, int $limit = self::FEED_LIMIT): Collection
    {
        return Notification::query()
            ->ownedBy($user)
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function unreadCount(User $user): int
    {
        return Notification::query()
            ->ownedBy($user)
            ->unread()
            ->count();
    }

    /**
     * Oznacza jedno powiadomienie jako przeczytane. Operacja jest idempotentna —
     * front wysyła ją optymistycznie i powtórne kliknięcie nie może nadpisać
     * pierwotnej daty przeczytania.
     *
     * @throws ModelNotFoundException Gdy powiadomienie nie należy do użytkownika.
     */
    public function markAsRead(int $id, User $user): Notification
    {
        $notification = Notification::query()
            ->ownedBy($user)
            ->whereKey($id)
            ->first();

        if ($notification === null) {
            throw (new ModelNotFoundException())->setModel(Notification::class, [$id]);
        }

        if (! $notification->isRead()) {
            $notification->read_at = Date::now();
            $notification->save();
        }

        return $notification;
    }

    /**
     * @return int Liczba powiadomień, które faktycznie zmieniły stan.
     */
    public function markAllAsRead(User $user): int
    {
        return Notification::query()
            ->ownedBy($user)
            ->unread()
            ->update(['read_at' => Date::now()]);
    }
}
