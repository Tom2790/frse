<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    protected $model = Note::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => rtrim($this->faker->sentence(random_int(3, 6)), '.'),
            'content' => $this->faker->paragraphs(random_int(1, 3), true),
            'is_pinned' => $this->faker->boolean(20),
            'created_at' => $this->faker->dateTimeBetween('-3 months'),
        ];
    }

    /**
     * Notatka przypięta.
     */
    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    /**
     * Notatka nieprzypięta.
     *
     * Właściciela wskazujemy wbudowanym `Note::factory()->for($user)` — relacja
     * `user()` na modelu wystarcza, żeby fabryka rozpoznała powiązanie.
     */
    public function unpinned(): static
    {
        return $this->state(['is_pinned' => false]);
    }
}
