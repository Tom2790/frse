<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\NoteLimitExceededException;
use App\Models\User;
use App\Repositories\Contracts\NoteRepositoryInterface;
use App\Repositories\EloquentNoteRepository;
use App\Services\NoteService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testy warstwy serwisowej (Zadanie 2) — bez bazy danych.
 *
 * Serwis dostaje atrapę repozytorium, więc sprawdzamy wyłącznie reguły biznesowe,
 * a nie SQL. To jest praktyczna korzyść z wzorca Repository: logika biznesowa
 * jest testowalna w izolacji.
 */
class NoteServiceTest extends TestCase
{
    private InMemoryNoteRepository $repository;

    private NoteService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->repository = new InMemoryNoteRepository();
        $this->service = new NoteService($this->repository);

        // Model bez zapisu do bazy — wystarczy nam identyfikator.
        $this->user = User::factory()->makeOne();
        $this->user->id = 1;
    }

    #[Test]
    public function kontener_wiaze_interfejs_z_implementacja_eloquent(): void
    {
        $this->assertInstanceOf(
            EloquentNoteRepository::class,
            $this->app->make(NoteRepositoryInterface::class),
        );
    }

    #[Test]
    public function tworzy_notatke_z_domyslnym_brakiem_przypiecia(): void
    {
        $note = $this->service->create(['title' => 'Tytuł', 'content' => 'Treść'], $this->user);

        $this->assertSame('Tytuł', $note->title);
        $this->assertFalse($note->is_pinned);
        $this->assertSame($this->user->id, $note->user_id);
        $this->assertSame(1, $this->repository->createCalls);
    }


    #[Test]
    public function pilnuje_limitu_notatek_na_uzytkownika(): void
    {
        $this->repository->seed($this->user, NoteService::MAX_NOTES_PER_USER);

        $this->assertSame(0, $this->service->remainingQuota($this->user));

        try {
            $this->service->create(['title' => 'Ponad limit', 'content' => 'Treść'], $this->user);
            $this->fail('Oczekiwano wyjątku NoteLimitExceededException.');
        } catch (NoteLimitExceededException $exception) {
            $this->assertSame(NoteService::MAX_NOTES_PER_USER, $exception->limit);
            $this->assertSame(422, $exception->render()->getStatusCode());
        }

        // Przy przekroczonym limicie repozytorium nie jest w ogóle wołane o zapis.
        $this->assertSame(0, $this->repository->createCalls);
    }

    #[Test]
    public function pozwala_dodac_notatke_dokladnie_do_limitu(): void
    {
        $this->repository->seed($this->user, NoteService::MAX_NOTES_PER_USER - 1);

        $this->assertSame(1, $this->service->remainingQuota($this->user));

        $note = $this->service->create(['title' => 'Ostatnia', 'content' => 'Treść'], $this->user);

        $this->assertSame('Ostatnia', $note->title);
        $this->assertSame(0, $this->service->remainingQuota($this->user));
    }

    #[Test]
    public function rozmiar_strony_jest_domyslny_i_ograniczony(): void
    {
        $this->repository->seed($this->user, 60);

        $this->assertSame(NoteService::DEFAULT_PER_PAGE, $this->service->paginate($this->user)->perPage());
        $this->assertSame(NoteService::MAX_PER_PAGE, $this->service->paginate($this->user, 999)->perPage());
        $this->assertSame(1, $this->service->paginate($this->user, 0)->perPage());
        $this->assertSame(5, $this->service->paginate($this->user, 5)->perPage());
    }

    #[Test]
    public function odrzuca_dostep_do_notatki_innego_uzytkownika(): void
    {
        $note = $this->service->create(['title' => 'Moja', 'content' => 'Treść'], $this->user);

        $intruder = User::factory()->makeOne();
        $intruder->id = 999;

        $this->expectException(ModelNotFoundException::class);

        $this->service->findOrFail($note->id, $intruder);
    }

    #[Test]
    public function aktualizacja_ignoruje_pola_poza_lista_dozwolonych(): void
    {
        $note = $this->service->create(['title' => 'Tytuł', 'content' => 'Treść'], $this->user);

        $updated = $this->service->update(
            $note->id,
            ['title' => 'Zmieniony', 'user_id' => 999, 'id' => 12345],
            $this->user,
        );

        $this->assertSame('Zmieniony', $updated->title);
        $this->assertSame($this->user->id, $updated->user_id);
        $this->assertSame($note->id, $updated->id);
    }

    #[Test]
    public function liczy_notatki_i_przypiecia(): void
    {
        $this->service->create(['title' => 'Pierwsza', 'content' => 'Treść'], $this->user);
        $this->service->create(['title' => 'Druga', 'content' => 'Treść', 'is_pinned' => true], $this->user);

        $this->assertSame(2, $this->service->count($this->user));
        $this->assertSame(1, $this->service->countPinned($this->user));
    }

    #[Test]
    public function usuniecie_nieistniejacej_notatki_konczy_sie_wyjatkiem(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->service->delete(404, $this->user);
    }
}
