<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\NoteCreated;
use App\Listeners\SendNoteCreatedEmail;
use App\Mail\NoteCreatedMail;
use App\Models\Note;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Mail po utworzeniu notatki: zdarzenie, kolejkowany listener, Mailable. */
class NoteCreatedNotificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function utworzenie_notatki_publikuje_zdarzenie(): void
    {
        Event::fake([NoteCreated::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/notes', [
            'title' => 'Notatka z e-mailem',
            'content' => 'Treść notatki.',
        ])->assertCreated();

        Event::assertDispatched(
            NoteCreated::class,
            fn (NoteCreated $event): bool => $event->note->id === $response->json('data.id'),
        );
    }

    #[Test]
    public function listener_jest_zarejestrowany_dla_zdarzenia(): void
    {
        Event::fake([NoteCreated::class]);

        // Laravel sam skanuje app/Listeners. Sprawdzamy, ze powiazanie faktycznie
        // istnieje, a nie ze powinno.
        Event::assertListening(NoteCreated::class, SendNoteCreatedEmail::class);
    }

    #[Test]
    public function listener_trafia_do_kolejki_a_nie_blokuje_odpowiedzi(): void
    {
        Queue::fake();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/notes', [
            'title' => 'Notatka z kolejką',
            'content' => 'Treść notatki.',
        ])->assertCreated();

        // Kolejkowany listener trafia do kolejki zapakowany w CallQueuedListener.
        Queue::assertPushed(
            \Illuminate\Events\CallQueuedListener::class,
            fn (\Illuminate\Events\CallQueuedListener $job): bool => $job->class === SendNoteCreatedEmail::class,
        );

        $this->assertInstanceOf(ShouldQueue::class, new SendNoteCreatedEmail());
    }

    #[Test]
    public function listener_wysyla_mail_do_wlasciciela_notatki(): void
    {
        Mail::fake();

        $owner = User::factory()->create(['email' => 'tomek-remlein@wp.pl']);
        $note = Note::factory()->for($owner)->create(['title' => 'Raport miesięczny']);

        (new SendNoteCreatedEmail())->handle(new NoteCreated($note));

        Mail::assertSent(
            NoteCreatedMail::class,
            fn (NoteCreatedMail $mail): bool => $mail->hasTo('tomek-remlein@wp.pl')
                && $mail->note->is($note),
        );
    }

    #[Test]
    public function mail_ma_poprawny_temat_i_skrocona_tresc(): void
    {
        $owner = User::factory()->create(['name' => 'Tomasz Remlein']);
        $note = Note::factory()->for($owner)->create([
            'title' => 'Bardzo ważna notatka',
            'content' => str_repeat('długa treść ', 60),
        ]);

        $mailable = new NoteCreatedMail($note);

        $mailable->assertHasSubject('Nowa notatka: Bardzo ważna notatka');
        $mailable->assertSeeInHtml('Tomasz Remlein');
        $mailable->assertSeeInHtml('Bardzo ważna notatka');

        // Tresc jest ucieta do 200 znakow plus wielokropek.
        $rendered = $mailable->render();
        $this->assertStringNotContainsString($note->content, $rendered);
        $this->assertStringContainsString('...', $rendered);
    }

    #[Test]
    public function pelna_sciezka_z_kolejka_synchroniczna_konczy_sie_wyslanym_mailem(): void
    {
        // Testy chodza na QUEUE_CONNECTION=sync, wiec bez Queue::fake() listener
        // wykonuje sie od razu i sprawdzamy caly lancuch.
        Mail::fake();

        $user = User::factory()->create(['email' => 'tomek-remlein@wp.pl']);
        Sanctum::actingAs($user);

        $this->postJson('/api/notes', [
            'title' => 'Notatka end-to-end',
            'content' => 'Treść notatki.',
        ])->assertCreated();

        Mail::assertSent(NoteCreatedMail::class, fn (NoteCreatedMail $mail): bool => $mail->hasTo('tomek-remlein@wp.pl'));
    }
}
