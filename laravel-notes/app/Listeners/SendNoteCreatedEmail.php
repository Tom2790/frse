<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\NoteCreated;
use App\Mail\NoteCreatedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Mail do wlasciciela nowej notatki.
 *
 * ShouldQueue, bo POST /api/notes nie ma czekac na SMTP: zapis notatki to milisekundy,
 * wysylka maila setki milisekund albo wiecej. Dodatkowo padniety SMTP nie moze zamienic
 * poprawnie zapisanej notatki w blad 500 - w kolejce proba jest ponawiana, a po
 * wyczerpaniu prob zadanie laduje w failed_jobs, a notatka zostaje.
 *
 * Cena: mail wychodzi z opoznieniem i wymaga dzialajacego queue:work.
 */
class SendNoteCreatedEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /** Gdy SMTP chwilowo nie odpowiada. */
    public int $tries = 3;

    /**
     * Odstepy miedzy probami w sekundach.
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function handle(NoteCreated $event): void
    {
        // loadMissing, a nie ->user: relacja moze byc juz wczytana (zapis w repozytorium)
        // albo nie (po odtworzeniu modelu z kolejki). Dziala w obu przypadkach i nie
        // narusza zakazu leniwego ladowania.
        $owner = $event->note->loadMissing('user')->user;

        // Miedzy publikacja zdarzenia a obsluga zadania konto moglo zostac usuniete.
        if ($owner === null) {
            return;
        }

        Mail::to($owner)->send(new NoteCreatedMail($event->note));
    }

    /** Po wyczerpaniu prob. Zadanie jest juz w failed_jobs. */
    public function failed(NoteCreated $event, Throwable $exception): void
    {
        logger()->error('Nie udało się wysłać e-maila o nowej notatce.', [
            'note_id' => $event->note->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
