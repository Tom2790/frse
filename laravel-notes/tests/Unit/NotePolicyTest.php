<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Note;
use App\Models\User;
use App\Policies\NotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Polityka dostępu do notatek (Zadanie 1).
 *
 * Testujemy ją bezpośrednio i przez `Gate`, bo w normalnym przepływie HTTP cudza
 * notatka jest odsiewana już przez repozytorium (404) i polityka nie zdąży odmówić.
 * To druga warstwa zabezpieczenia — musi być sprawdzona osobno.
 */
class NotePolicyTest extends TestCase
{
    use RefreshDatabase;

    private NotePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new NotePolicy();
    }

    #[Test]
    public function wlasciciel_moze_ogladac_edytowac_i_usuwac(): void
    {
        $owner = User::factory()->create();
        $note = Note::factory()->for($owner)->create();

        $this->assertTrue($this->policy->view($owner, $note));
        $this->assertTrue($this->policy->update($owner, $note));
        $this->assertTrue($this->policy->delete($owner, $note));
    }

    #[Test]
    public function obcy_uzytkownik_nie_moze_nic(): void
    {
        $intruder = User::factory()->create();
        $note = Note::factory()->for(User::factory()->create())->create();

        $this->assertFalse($this->policy->view($intruder, $note));
        $this->assertFalse($this->policy->update($intruder, $note));
        $this->assertFalse($this->policy->delete($intruder, $note));
    }

    #[Test]
    public function polityka_jest_zarejestrowana_w_gate(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $note = Note::factory()->for($owner)->create();

        // Dowód, że atrybut #[UsePolicy] na modelu faktycznie działa.
        $this->assertTrue(Gate::forUser($owner)->allows('update', $note));
        $this->assertFalse(Gate::forUser($intruder)->allows('update', $note));
        $this->assertTrue(Gate::forUser($intruder)->allows('viewAny', Note::class));
        $this->assertTrue(Gate::forUser($intruder)->allows('create', Note::class));
    }
}
