# Zadanie 5b — dlaczego listener implementuje `ShouldQueue`

Zadanie kończy się polem „Dowiedz się, dlaczego Listener powinien implementować
ShouldQueue, a nie być synchroniczny”. Odpowiedź:

## 1. Czas odpowiedzi API

Bez kolejki `POST /api/notes` czeka na całą rozmowę z serwerem SMTP: DNS, TCP,
TLS, `EHLO`, `MAIL FROM`, `DATA`. Zapis notatki to jednostki milisekund, wysyłka
e-maila — setki, a przy wolnym lub przeciążonym SMTP nawet sekundy. Klient płaci
tym czasem za operację, na której wynik wcale nie czeka.

Zmierzone w tym projekcie (`MAIL_MAILER=log`, czyli najtańszy możliwy „transport”):
listener zajął **150 ms**. Przy realnym SMTP byłoby wielokrotnie więcej — i cały
ten czas doszedłby do odpowiedzi 201.

## 2. Odporność na awarię usługi zewnętrznej

Wersja synchroniczna: SMTP nie odpowiada → wyjątek → **500 na już poprawnie
zapisanej notatce**. Klient widzi błąd, prawdopodobnie ponawia żądanie i tworzy
duplikat.

Wersja kolejkowana: notatka jest zapisana, API zwraca 201, a nieudana próba
wysyłki jest ponawiana zgodnie z konfiguracją listenera:

```php
public int $tries = 3;
public array $backoff = [10, 60, 300];   // sekundy między próbami
```

Po wyczerpaniu prób zadanie trafia do `failed_jobs` i wywoływana jest metoda
`failed()`, która loguje kontekst. Nic nie ginie po cichu i nic nie psuje
odpowiedzi HTTP.

## 3. Rozdzielenie odpowiedzialności

Żądanie `POST /api/notes` ma jedno zadanie: utworzyć zasób. Powiadomienie
e-mailem to **efekt uboczny** — nie powinien decydować o tym, czy operacja
biznesowa się udała. Kolejka wymusza tę granicę na poziomie architektury,
a nie tylko dobrych intencji.

## 4. Skalowanie

Workery kolejki są osobnymi procesami. Można ich dodać przy dużym ruchu
e-mailowym bez skalowania warstwy HTTP i odwrotnie. Przy listenerze
synchronicznym każdy wolny SMTP zjada workera PHP-FPM, który mógłby w tym czasie
obsługiwać żądania.

## Koszty tej decyzji

Uczciwie: `ShouldQueue` nie jest darmowy.

- **Wymaga działającego workera.** Bez `php artisan queue:work` e-mail nigdy nie
  wyjdzie, a zadanie będzie leżeć w tabeli `jobs`. Na produkcji to znaczy:
  supervisor/systemd i monitoring kolejki.
- **E-mail wychodzi z opóźnieniem.** Zwykle poniżej sekundy, ale nie natychmiast.
- **Argumenty muszą być serializowalne.** Dlatego zdarzenie używa
  `SerializesModels` — do kolejki trafia klucz modelu, a listener odświeża go
  z bazy. Zapobiega to też wysyłce na podstawie nieaktualnej migawki obiektu.
- **Trudniejsze debugowanie.** Wyjątek nie pojawia się w odpowiedzi HTTP, tylko
  w logu workera i w `failed_jobs`.

W testach kompromis nie boli: `phpunit.xml` ustawia `QUEUE_CONNECTION=sync`, więc
łańcuch wykonuje się od razu i da się przetestować end-to-end
(`tests/Feature/NoteCreatedNotificationTest.php`). Osobny test z `Queue::fake()`
sprawdza to, na czym nam zależy w produkcji — że listener **trafia do kolejki**,
a nie blokuje odpowiedzi.

## Kiedy synchronicznie jest w porządku

Gdy listener nie rozmawia z niczym zewnętrznym i jest szybki — np. aktualizacja
licznika w bazie albo wpis do logu audytowego w tej samej transakcji. Kolejka
dodałaby wtedy tylko infrastrukturę i opóźnienie, nie kupując nic w zamian.
Reguła praktyczna: **kolejkuj wszystko, co wychodzi poza proces** (SMTP, HTTP do
zewnętrznego API, generowanie PDF, przetwarzanie obrazów).

## Zweryfikowany przepływ

```
POST /api/notes                    → 201 natychmiast
  └─ NoteService::create()
       └─ event(new NoteCreated($note))
            └─ CallQueuedListener w tabeli `jobs`      ← odpowiedź już wysłana

php artisan queue:work
  └─ SendNoteCreatedEmail::handle()  (150 ms)
       └─ Mail::to($note->user)->send(new NoteCreatedMail($note))
            └─ storage/logs/laravel.log:
               Subject: Nowa notatka: Notatka testowa z kolejka
```
