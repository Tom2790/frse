<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Note;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testy API notatek (Zadanie 1) — pełny cykl życia zasobu, izolacja danych,
 * walidacja i paginacja.
 */
class NoteApiTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- tworzenie

    #[Test]
    public function tworzy_notatke_dla_zalogowanego_uzytkownika(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notes', [
            'title' => 'Lista zakupów',
            'content' => 'Mleko, chleb, kawa.',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Lista zakupów')
            ->assertJsonPath('data.content', 'Mleko, chleb, kawa.')
            ->assertJsonPath('data.is_pinned', false)
            ->assertJsonStructure(['data' => ['id', 'title', 'content', 'is_pinned', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('notes', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
            'title' => 'Lista zakupów',
            'is_pinned' => false,
        ]);
    }

    #[Test]
    public function tworzenie_ignoruje_probe_podstawienia_wlasciciela(): void
    {
        $user = User::factory()->create();
        $victim = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notes', [
            'title' => 'Podstawiona notatka',
            'content' => 'Treść.',
            'user_id' => $victim->id,
        ]);

        $response->assertCreated();

        // Właściciela ustala serwis na podstawie sesji/tokenu, nie ciało żądania.
        $this->assertDatabaseHas('notes', [
            'id' => $response->json('data.id'),
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('notes', ['user_id' => $victim->id]);
    }

    #[Test]
    public function odrzuca_niepoprawne_dane_z_kodem_422_i_czytelnymi_komunikatami(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson('/api/notes', ['title' => 'ab']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content'])
            ->assertJsonPath('errors.title.0', 'Tytuł musi mieć co najmniej 3 znaki.')
            ->assertJsonPath('errors.content.0', 'Treść notatki jest wymagana.');
    }


    // -------------------------------------------------------------------- lista

    #[Test]
    public function zwraca_liste_wlasnych_notatek_z_paginacja_po_15(): void
    {
        $user = User::factory()->create();
        Note::factory()->count(23)->unpinned()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notes');

        $response->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 23);

        $this->getJson('/api/notes?page=2')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('meta.current_page', 2);
    }

    #[Test]
    public function lista_nie_zawiera_notatek_innych_uzytkownikow(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $own = Note::factory()->for($user)->create(['title' => 'Moja notatka']);
        $foreign = Note::factory()->for($other)->create(['title' => 'Cudza notatka']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notes');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonMissing(['id' => $foreign->id]);
    }

    #[Test]
    public function lista_zwraca_przypiete_na_gorze_i_licznik_przypietych(): void
    {
        $user = User::factory()->create();
        Note::factory()->count(4)->unpinned()->for($user)->create();
        $pinned = Note::factory()->count(2)->pinned()->for($user)->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/notes');

        $response->assertOk()
            ->assertJsonPath('data.0.is_pinned', true)
            ->assertJsonPath('data.1.is_pinned', true)
            ->assertJsonPath('data.2.is_pinned', false)
            ->assertJsonPath('meta.total', 6)
            ->assertJsonPath('meta.pinned_total', 2);

        $this->assertEqualsCanonicalizing(
            $pinned->pluck('id')->all(),
            [$response->json('data.0.id'), $response->json('data.1.id')],
        );
    }


    // ------------------------------------------------------- pojedynczy zasób

    #[Test]
    public function zwraca_wlasna_notatke(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create(['title' => 'Szczegóły']);
        Sanctum::actingAs($user);

        $this->getJson("/api/notes/{$note->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $note->id)
            ->assertJsonPath('data.title', 'Szczegóły');
    }

    #[Test]
    public function proba_dostepu_do_cudzej_notatki_konczy_sie_404(): void
    {
        $user = User::factory()->create();
        $foreign = Note::factory()->for(User::factory()->create())->create();
        Sanctum::actingAs($user);

        // 404, nie 403 — nie potwierdzamy istnienia cudzych zasobów.
        $this->getJson("/api/notes/{$foreign->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Nie znaleziono zasobu.');
    }

    #[Test]
    public function nie_mozna_zaktualizowac_ani_usunac_cudzej_notatki(): void
    {
        $user = User::factory()->create();
        $foreign = Note::factory()->for(User::factory()->create())->create(['title' => 'Nietykalna']);
        Sanctum::actingAs($user);

        $this->putJson("/api/notes/{$foreign->id}", ['title' => 'Przejęta notatka'])
            ->assertNotFound();

        $this->deleteJson("/api/notes/{$foreign->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('notes', [
            'id' => $foreign->id,
            'title' => 'Nietykalna',
        ]);
    }

    #[Test]
    public function zwraca_404_dla_nieistniejacej_notatki(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notes/999999')->assertNotFound();
    }

    // ------------------------------------------------------------ aktualizacja

    #[Test]
    public function aktualizuje_wlasna_notatke(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->unpinned()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Nowy tytuł',
            'content' => 'Nowa treść.',
            'is_pinned' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nowy tytuł')
            ->assertJsonPath('data.is_pinned', true);

        $this->assertDatabaseHas('notes', [
            'id' => $note->id,
            'title' => 'Nowy tytuł',
            'is_pinned' => true,
        ]);
    }

    #[Test]
    public function pozwala_przelaczyc_samo_przypiecie_bez_reszty_pol(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->unpinned()->for($user)->create(['title' => 'Bez zmian']);
        Sanctum::actingAs($user);

        // Tak działa optymistyczny toggle w komponencie NoteManager.vue.
        $this->patchJson("/api/notes/{$note->id}", ['is_pinned' => true])
            ->assertOk()
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.title', 'Bez zmian');
    }

    #[Test]
    public function odrzuca_pusta_aktualizacje(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->putJson("/api/notes/{$note->id}", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    // --------------------------------------------------------------- usuwanie

    #[Test]
    public function usuwa_wlasna_notatke_i_zwraca_204(): void
    {
        $user = User::factory()->create();
        $note = Note::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->deleteJson("/api/notes/{$note->id}")->assertNoContent();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
    }

    // ------------------------------------------------------- uwierzytelnianie

    #[Test]
    public function niezalogowany_uzytkownik_nie_ma_dostepu_do_zadnego_endpointu(): void
    {
        $note = Note::factory()->for(User::factory()->create())->create();

        $this->getJson('/api/notes')->assertUnauthorized();
        $this->postJson('/api/notes', ['title' => 'Test', 'content' => 'Test'])->assertUnauthorized();
        $this->getJson("/api/notes/{$note->id}")->assertUnauthorized();
        $this->putJson("/api/notes/{$note->id}", ['title' => 'Test'])->assertUnauthorized();
        $this->deleteJson("/api/notes/{$note->id}")->assertUnauthorized();
    }
}
