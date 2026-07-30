<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Strona hostujaca widget Vue i logowanie sesyjne. */
class NotesPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gosc_jest_przekierowany_na_logowanie(): void
    {
        $this->get('/notes')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/notes');
    }

    #[Test]
    public function zalogowany_widzi_strone_z_widgetem(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'Tomasz Remlein']))
            ->get('/notes')
            ->assertOk()
            ->assertSee('id="app"', false)
            ->assertSee('<note-manager>', false)
            ->assertSee('<notification-bell>', false)
            ->assertSee('Tomasz Remlein');
    }

    #[Test]
    public function logowanie_sesyjne_dziala_i_zmienia_id_sesji(): void
    {
        User::factory()->create([
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ]);

        $sessionIdBefore = session()->getId();

        $this->post('/login', [
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ])->assertRedirect(route('notes.index'));

        $this->assertAuthenticated();
        $this->assertNotSame($sessionIdBefore, session()->getId(), 'sesja musi zostac zregenerowana');
    }

    #[Test]
    public function zle_dane_logowania_wracaja_z_bledem_walidacji(): void
    {
        User::factory()->create([
            'email' => 'tomek-remlein@wp.pl',
            'password' => 'tajnehaslo1',
        ]);

        $this->from('/login')
            ->post('/login', ['email' => 'tomek-remlein@wp.pl', 'password' => 'zle'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Nieprawidłowy e-mail lub hasło.']);

        $this->assertGuest();
    }

    #[Test]
    public function wylogowanie_konczy_sesje(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/logout')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    #[Test]
    public function widget_uwierzytelnia_sie_do_api_sesja(): void
    {
        // Widget nie ma tokenu - jedzie na ciasteczku sesji. To sprawdza, ze
        // statefulApi() w bootstrap/app.php faktycznie dziala.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
