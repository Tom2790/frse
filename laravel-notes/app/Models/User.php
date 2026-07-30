<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int    $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Note>         $notes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Notification> $notifications
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    // Tokeny osobiste Sanctuma: createToken(), currentAccessToken().
    use HasApiTokens;

    // Świadomie BEZ traitu `Notifiable`: nie korzystamy z systemu notyfikacji Laravela,
    // a jego relacja `notifications()` (morphMany do własnej tabeli Laravela) kolidowałaby
    // z naszą tabelą powiadomień z Zadania 5a. E-maile wysyłamy przez `Mail::to($user)`,
    // co tego traitu nie wymaga.

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<Note, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    /**
     * Powiadomienia w aplikacji (Zadanie 5a) — najnowsze pierwsze.
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class)->latest();
    }
}
