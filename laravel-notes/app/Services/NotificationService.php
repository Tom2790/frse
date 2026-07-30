<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;

/**
 * Bez wlasnego repozytorium: jedna tabela i trzy zapytania, wiec kolejny interfejs
 * bylby warstwa bez tresci. Zasada zostaje ta sama - kontroler nie dotyka modeli.
 */
final class NotificationService
{
    /** Ile powiadomien pokazuje dzwonek. */
    public const int FEED_LIMIT = 20;

    /**
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
     * Idempotentne: front wysyla to optymistycznie, a powtorne klikniecie nie moze
     * nadpisac pierwotnej daty przeczytania.
     *
     * @throws ModelNotFoundException
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

    /** @return int Ile powiadomien faktycznie zmienilo stan. */
    public function markAllAsRead(User $user): int
    {
        return Notification::query()
            ->ownedBy($user)
            ->unread()
            ->update(['read_at' => Date::now()]);
    }
}
