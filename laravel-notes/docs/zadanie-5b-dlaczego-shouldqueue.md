# Zadanie 5b - dlaczego listener implementuje `ShouldQueue`

Ostatni punkt zadania brzmi „dowiedz się, dlaczego Listener powinien implementować
ShouldQueue, a nie być synchroniczny". Odpowiedź w czterech punktach.

**1. Czas odpowiedzi.** Bez kolejki `POST /api/notes` czeka na całą rozmowę z SMTP: DNS,
TCP, TLS, `EHLO`, `MAIL FROM`, `DATA`. Zapis notatki to milisekundy, wysyłka maila setki
milisekund, a przy wolnym serwerze nawet sekundy. Klient płaci tym czasem za coś, na czego
wynik nie czeka. W tym projekcie listener zajął 150 ms i to na `MAIL_MAILER=log`, czyli
najtańszym możliwym „transporcie". Przy realnym SMTP byłoby wielokrotnie więcej.

**2. Odporność na padnięcie usługi.** Synchronicznie: SMTP nie odpowiada, leci wyjątek
i klient dostaje 500 na już poprawnie zapisanej notatce. Prawdopodobnie ponowi żądanie
i zrobi duplikat. W kolejce nieudana próba jest ponawiana:

```php
public int $tries = 3;
public array $backoff = [10, 60, 300];
```

Po wyczerpaniu prób zadanie ląduje w `failed_jobs` i odpala się metoda `failed()`, która
loguje kontekst. Nic nie ginie po cichu i nic nie psuje odpowiedzi HTTP.

**3. Podział odpowiedzialności.** Żądanie `POST /api/notes` ma jedno zadanie: utworzyć
zasób. Mail to efekt uboczny i nie powinien decydować o tym, czy operacja się udała.
Kolejka wymusza tę granicę na poziomie kodu, nie tylko dobrych intencji.

**4. Skalowanie.** Workery to osobne procesy, więc można ich dodać przy dużym ruchu
mailowym bez skalowania warstwy HTTP. Przy listenerze synchronicznym każdy wolny SMTP
zajmuje workera PHP-FPM, który mógłby w tym czasie obsługiwać żądania.

## Co to kosztuje

Uczciwie - `ShouldQueue` nie jest darmowy:

- Wymaga działającego `php artisan queue:work`. Bez tego mail nigdy nie wyjdzie, a zadanie
  będzie leżeć w tabeli `jobs`. Na produkcji oznacza to supervisora i monitoring kolejki.
- Mail wychodzi z opóźnieniem. Zwykle poniżej sekundy, ale nie natychmiast.
- Argumenty muszą być serializowalne. Dlatego zdarzenie używa `SerializesModels` - do
  kolejki idzie klucz modelu, a listener odświeża go z bazy. Zapobiega to też wysyłce
  na podstawie nieaktualnej migawki obiektu.
- Trudniejszy debug. Wyjątek nie pojawia się w odpowiedzi HTTP, tylko w logu workera
  i w `failed_jobs`.

W testach to nie boli, bo `phpunit.xml` ustawia `QUEUE_CONNECTION=sync` - łańcuch wykonuje
się od razu i da się sprawdzić end to end. Osobny test z `Queue::fake()` pilnuje tego, na
czym zależy nam na produkcji: że listener trafia do kolejki, a nie blokuje odpowiedzi.

## Kiedy synchronicznie jest w porządku

Gdy listener nie gada z niczym zewnętrznym i jest szybki - aktualizacja licznika w bazie,
wpis do logu audytowego w tej samej transakcji. Kolejka dodałaby wtedy tylko infrastrukturę
i opóźnienie. Reguła praktyczna: kolejkuj wszystko, co wychodzi poza proces (SMTP, HTTP do
zewnętrznego API, generowanie PDF, obróbka obrazów).

## Sprawdzony przepływ

```
POST /api/notes                          201 od razu
  NoteService::create()
    event(new NoteCreated($note))
      CallQueuedListener w tabeli jobs    <- odpowiedź już poszła do klienta

php artisan queue:work
  SendNoteCreatedEmail::handle()          150 ms
    Mail::to($note->user)->send(new NoteCreatedMail($note))
      storage/logs/laravel.log:
      Subject: Nowa notatka: Notatka testowa z kolejka
```
