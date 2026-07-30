<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Powiadomienie w aplikacji (Zadanie 5a).
 *
 * @property int                              $id
 * @property int                              $user_id
 * @property string                           $type
 * @property string                           $title
 * @property string                           $body
 * @property \Illuminate\Support\Carbon|null  $read_at
 * @property \Illuminate\Support\Carbon|null  $created_at
 * @property-read User                        $user
 */
#[Fillable(['type', 'title', 'body', 'read_at'])]
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    /** Tabela ma tylko `created_at` — wyłączamy zapis `updated_at`. */
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Notification>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<Notification>  $query
     */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }
}
