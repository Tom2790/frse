<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['note.assigned', 'note.commented', 'system.info']);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'title' => $this->titleFor($type),
            'body' => $this->faker->sentence(random_int(10, 20)),
            'read_at' => $this->faker->boolean(40) ? $this->faker->dateTimeBetween('-2 days') : null,
            'created_at' => $this->faker->dateTimeBetween('-7 days'),
        ];
    }

    public function unread(): static
    {
        return $this->state(['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn (): array => [
            'read_at' => $this->faker->dateTimeBetween('-1 day'),
        ]);
    }

    private function titleFor(string $type): string
    {
        return match ($type) {
            'note.assigned' => 'Przypisano Ci nową notatkę',
            'note.commented' => 'Nowy komentarz do Twojej notatki',
            default => 'Informacja systemowa',
        };
    }
}
