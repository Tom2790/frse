<?php

declare(strict_types=1);

namespace App\Models;

use App\Policies\NotePolicy;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $content
 * @property bool $is_pinned
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read User $user
 */
// user_id poza Fillable - wlasciciela ustawiamy relacja, nie z ciala zadania.
#[Fillable(['title', 'content', 'is_pinned'])]
#[UsePolicy(NotePolicy::class)]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
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
     * Zawezenie do jednego wlasciciela. Podstawa izolacji danych.
     *
     * @param  Builder<Note>  $query
     */
    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * Domyslna kolejnosc listy: przypiete na gorze, dalej od najnowszych.
     *
     * @param  Builder<Note>  $query
     */
    #[Scope]
    protected function pinnedFirst(Builder $query): void
    {
        $query->orderByDesc('is_pinned')->orderByDesc('created_at')->orderByDesc('id');
    }
}
