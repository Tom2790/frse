<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dwa konta z przewidywalnym loginem, zeby dalo sie od razu wejsc do widgetu
 * i sprawdzic izolacje danych miedzy nimi.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = $this->createUser('Tomasz Remlein', 'tomek-remlein@wp.pl');
        $other = $this->createUser('Jan Kowalski', 'jan.kowalski@example.com');

        // 23 notatki, zeby paginacja po 15 miala druga strone.
        Note::factory()->count(3)->pinned()->for($demo)->create();
        Note::factory()->count(20)->unpinned()->for($demo)->create();

        // Konto kontrolne. Jego notatki nie moga pojawic sie na liscie konta demo.
        Note::factory()->count(7)->for($other)->create();

        // 5 powiadomien dla konta demo, z czego 3 nieprzeczytane.
        Notification::factory()->count(3)->unread()->for($demo)->create();
        Notification::factory()->count(2)->read()->for($demo)->create();

        Notification::factory()->count(4)->for($other)->create();

        $this->command?->info('Konta: tomek-remlein@wp.pl i jan.kowalski@example.com, hasło: password');
    }

    private function createUser(string $name, string $email): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
        ]);
    }
}
