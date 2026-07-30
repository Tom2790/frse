<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Note;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Dane demonstracyjne (Faker).
 *
 * Zakłada dwa konta z przewidywalnym loginem, żeby dało się od razu wejść do widgetu
 * i sprawdzić izolację danych: notatki drugiego użytkownika NIE mogą być widoczne
 * po zalogowaniu na pierwsze konto.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demo = $this->createUser('Anna Testowa', 'demo@example.com');
        $other = $this->createUser('Bartek Obcy', 'obcy@example.com');

        // Konto demo: 23 notatki, żeby paginacja (15 na stronę) miała drugą stronę.
        Note::factory()->count(3)->pinned()->for($demo)->create();
        Note::factory()->count(20)->unpinned()->for($demo)->create();

        // Konto kontrolne — jego notatki nie mogą pojawić się na liście konta demo.
        Note::factory()->count(7)->for($other)->create();

        // Zadanie 5a: 5 powiadomień dla konta demo, z czego 3 nieprzeczytane.
        Notification::factory()->count(3)->unread()->for($demo)->create();
        Notification::factory()->count(2)->read()->for($demo)->create();

        Notification::factory()->count(4)->for($other)->create();

        $this->command?->info('Konta demo: demo@example.com i obcy@example.com — hasło: password');
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
