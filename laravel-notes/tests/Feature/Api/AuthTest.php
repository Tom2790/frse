<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Rejestracja i logowanie na tokenach Sanctuma. */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limit 6 zadan na minute jest celowy, ale w jednym tescie uderzamy w ten
        // sam endpoint kilka razy z tego samego IP.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    #[Test]
    public function rejestruje_uzytkownika_i_zwraca_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Tomasz Remlein',
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
            'password_confirmation' => 'tajnehaslo1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'tomek-remlein@wp.pl')
            ->assertJsonStructure(['message', 'user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'tomek-remlein@wp.pl']);
        $this->assertNotEmpty($response->json('token'));

        // Haslo nigdy nie wraca w odpowiedzi.
        $this->assertArrayNotHasKey('password', $response->json('user'));
    }

    #[Test]
    public function token_z_rejestracji_daje_dostep_do_api(): void
    {
        $token = $this->postJson('/api/register', [
            'name' => 'Tomasz Remlein',
            'email' => 'tomek-remlein@wp.pl',
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
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'tomek-remlein@wp.pl',
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
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ]);

        $wrongPassword = $this->postJson('/api/login', [
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'zlehaslo',
        ]);

        $noAccount = $this->postJson('/api/login', [
            'email' => 'nieznany@example.com',
            'password' => 'cokolwiek1',
        ]);

        $wrongPassword->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Nieprawidłowy e-mail lub hasło.');

        // Identyczna odpowiedz w obu przypadkach, wiec nie da sie zgadywac kont.
        $noAccount->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Nieprawidłowy e-mail lub hasło.');
    }

    #[Test]
    public function wylogowanie_uniewaznia_uzyty_token(): void
    {
        $user = User::factory()->create([
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ]);

        $token = $this->postJson('/api/login', [
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ])->json('token');

        $this->withToken($token)->postJson('/api/logout')->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // W testach aplikacja zyje miedzy zadaniami, wiec guard trzyma uzytkownika
        // rozwiazanego przy poprzednim zadaniu. W realnym HTTP kazde zadanie startuje
        // od zera, tutaj trzeba to wymusic recznie.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/notes')->assertUnauthorized();
    }
}
