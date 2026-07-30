<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rejestracja i logowanie przez Sanctum (Zadanie 1).
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limit 6 żądań na minutę jest celowy w produkcji, ale w jednym teście
        // uderzamy w ten sam endpoint kilka razy z tego samego „IP”.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    #[Test]
    public function rejestruje_uzytkownika_i_zwraca_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Anna Testowa',
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
            'password_confirmation' => 'tajnehaslo1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'anna@example.com')
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'anna@example.com']);
        $this->assertNotEmpty($response->json('token'));

        // Hasło nigdy nie wraca w odpowiedzi.
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    #[Test]
    public function token_z_rejestracji_daje_dostep_do_api(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Anna Testowa',
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
            'password_confirmation' => 'tajnehaslo1',
        ])->json('token');

        $this->withToken($token)
            ->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    #[Test]
    public function rejestracja_waliduje_dane(): void
    {
        User::factory()->create(['email' => 'zajety@example.com']);

        $this->postJson('/api/register', [
            'name' => 'A',
            'email' => 'zajety@example.com',
            'password' => 'krotkie',
            'password_confirmation' => 'inne',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password'])
            ->assertJsonPath('errors.email.0', 'Konto z tym adresem e-mail już istnieje.');
    }

    #[Test]
    public function loguje_uzytkownika_i_zwraca_token(): void
    {
        $user = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
            'device_name' => 'testy',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonStructure(['message', 'user', 'token']);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'testy',
        ]);
    }

    #[Test]
    public function nie_loguje_przy_zlym_hasle_i_nie_zdradza_czy_konto_istnieje(): void
    {
        User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
        ]);

        $wrongPassword = $this->postJson('/api/login', [
            'email' => 'anna@example.com',
            'password' => 'zlehaslo',
        ]);

        $noAccount = $this->postJson('/api/login', [
            'email' => 'nieznany@example.com',
            'password' => 'cokolwiek1',
        ]);

        $wrongPassword->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Nieprawidłowy e-mail lub hasło.');

        // Identyczna odpowiedź w obu przypadkach — brak enumeracji kont.
        $noAccount->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Nieprawidłowy e-mail lub hasło.');
    }

    #[Test]
    public function wylogowanie_uniewaznia_uzyty_token(): void
    {
        $user = User::factory()->create([
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'anna@example.com',
            'password' => 'tajnehaslo1',
        ])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // W testach aplikacja żyje między żądaniami, więc guard Sanctuma trzyma
        // użytkownika rozwiązanego przy poprzednim żądaniu. W realnym HTTP każde
        // żądanie startuje od zera — tutaj musimy to wymusić ręcznie.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/notes')->assertUnauthorized();
    }
}
