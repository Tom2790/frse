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
 * Wysyła e-mail do właściciela nowo utworzonej notatki.
 *
 * DLACZEGO `ShouldQueue`, a nie synchronicznie?
 *
 * 1. Czas odpowiedzi. Bez kolejki `POST /api/notes` czeka na rozmowę z serwerem SMTP.
 *    Zapis notatki to jednostki milisekund, wysyłka e-maila — setki milisekund,
 *    a przy wolnym SMTP nawet sekundy. Klient płaci tym czasem za coś, na co nie czeka.
 * 2. Odporność. Gdy SMTP jest chwilowo niedostępny, wersja synchroniczna wywala 500
 *    na już poprawnie zapisanej notatce. W kolejce nieudana próba jest ponawiana
 *    (`$tries`/`backoff`), a po wyczerpaniu prób trafia do `failed_jobs` — notatka
 *    pozostaje utworzona, a API zwraca 201.
 * 3. Rozdzielenie odpowiedzialności. Żądanie HTTP odpowiada za zapis zasobu.
 *    Powiadomienie to efekt uboczny — nie powinien decydować o wyniku żądania.
 * 4. Skala. Kolejkę można obsłużyć osobnymi workerami i skalować niezależnie od HTTP.
 *
 * Kompromis: e-mail wychodzi z opóźnieniem i wymaga działającego `queue:work`.
 * W testach używamy `Mail::fake()` / `Queue::fake()`, więc nic tego nie wymaga.
 */
class SendNoteCreatedEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /** Liczba prób, jeśli np. SMTP chwilowo nie odpowiada. */
    public int $tries = 3;

    /**
     * Rosnące odstępy między próbami (sekundy).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function handle(NoteCreated $event): void
    {
        // `loadMissing` zamiast `->user`: relacja może być już wczytana (zapis w repozytorium)
        // albo nie (po odtworzeniu modelu z kolejki). Jawne doładowanie jest bezpieczne
        // w obu przypadkach i nie narusza zakazu leniwego ładowania.
        $owner = $event->note->loadMissing('user')->user;

        // Właściciel mógł zostać usunięty między publikacją zdarzenia a obsługą
        // zadania z kolejki — wtedy nie ma do kogo wysłać maila.
        if ($owner === null) {
            return;
        }

        Mail::to($owner)->send(new NoteCreatedMail($event->note));
    }

    /**
     * Wywoływane po wyczerpaniu wszystkich prób — zadanie jest już w `failed_jobs`.
     */
    public function failed(NoteCreated $event, Throwable $exception): void
    {
        logger()->error('Nie udało się wysłać e-maila o nowej notatce.', [
            'note_id' => $event->note->id,
            'exception' => $exception->getMessage(),
        ]);
    }
}
