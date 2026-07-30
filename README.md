# Zadania rekrutacyjne — Full Stack Developer (Laravel / PHP / JS / Vue)

Rozwiązania wszystkich 5 zadań (1–4 obowiązkowe, 5a i 5b opcjonalne).

```
laravel-notes/          Zadania 1, 2, 4, 5a, 5b — aplikacja Laravel 13 + Vue 3
zadanie-3-task-queue/   Zadanie 3 — czysty JavaScript, bez zależności
```

Zadanie 3 jest osobnym pakietem, bo nie ma nic wspólnego z Laravelem. Pozostałe zadania
świadomie mieszkają w jednej aplikacji: Zadanie 2 refaktoryzuje kod z Zadania 1,
a Zadania 4 i 5 konsumują to samo API.

Każde zadanie to osobny commit i każdy z nich jest samodzielnie zielony — jego testy
przechodzą na stanie repozytorium z tego właśnie commita, bez kodu z zadań późniejszych:

```
docs: README z mapa zadan, instrukcja uruchomienia i decyzjami projektowymi
feat(zadanie-5b): e-mail o nowej notatce przez kolejke              49 testów
feat(zadanie-5a): powiadomienia w aplikacji + dzwonek Vue           42 testy
feat(zadanie-4):  widget Vue do zarzadzania notatkami w widoku Blade
refactor(zadanie-2): warstwa repozytorium i serwisu                 35 testów
feat(zadanie-1):  REST API notatek z uwierzytelnianiem Sanctum       24 testy
feat(zadanie-3):  kolejka asynchroniczna z priorytetami w czystym JS 10 testów
chore: szkielet Laravel 13 z Sanctum
```

Warto zajrzeć w diff commita Zadania 2 — pokazuje dokładnie tę refaktoryzację, o którą
prosi treść zadania: w commicie Zadania 1 `NoteController` rozmawia z Eloquentem
bezpośrednio, a Zadanie 2 przenosi to do repozytorium i serwisu.

## Mapa zadań → kod

| Zadanie | Najważniejsze pliki | Dokumentacja |
| --- | --- | --- |
| **1** REST API + Sanctum | `app/Http/Controllers/Api/{Auth,Note}Controller.php`, `app/Http/Requests/`, `app/Http/Resources/`, `app/Policies/NotePolicy.php`, `database/migrations/*_create_notes_table.php` | ten plik, sekcja „Zadanie 1” |
| **2** Repository + Service | `app/Repositories/Contracts/NoteRepositoryInterface.php`, `app/Repositories/EloquentNoteRepository.php`, `app/Services/NoteService.php`, `app/Providers/AppServiceProvider.php` | [`docs/zadanie-2-refaktoryzacja.md`](laravel-notes/docs/zadanie-2-refaktoryzacja.md) |
| **3** Kolejka async w JS | `zadanie-3-task-queue/src/TaskQueue.js` | [`zadanie-3-task-queue/README.md`](zadanie-3-task-queue/README.md) |
| **4** Widget Vue w Bladzie | `resources/js/components/{NoteManager,NoteForm}.vue`, `resources/js/app.js`, `resources/views/notes.blade.php` | ten plik, sekcja „Zadanie 4” |
| **5a** Dzwonek powiadomień | `resources/js/components/NotificationBell.vue`, `app/Http/Controllers/Api/NotificationController.php`, `app/Services/NotificationService.php` | ten plik, sekcja „Zadanie 5a” |
| **5b** E-mail przez kolejkę | `app/Events/NoteCreated.php`, `app/Listeners/SendNoteCreatedEmail.php`, `app/Mail/NoteCreatedMail.php` | [`docs/zadanie-5b-dlaczego-shouldqueue.md`](laravel-notes/docs/zadanie-5b-dlaczego-shouldqueue.md) |

## Uruchomienie

Wymagania: **PHP 8.4**, Composer 2, **Node 18+**. Baza to SQLite — nie trzeba MySQL-a.

```bash
# --- Zadania 1, 2, 4, 5a, 5b ---------------------------------------------
cd laravel-notes
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # 2 konta, 30 notatek, 9 powiadomień
npm install
npm run build                       # albo `npm run dev` przy pracy nad frontem
php artisan serve                   # http://127.0.0.1:8000

# w drugim terminalu — Zadanie 5b
php artisan queue:work
```

Logowanie: `demo@example.com` / `password` (pola są wstępnie wypełnione).
Drugie konto `obcy@example.com` służy do sprawdzenia izolacji danych — po zalogowaniu
na nie widać zupełnie inne notatki.

> **Uwaga o porcie.** Widget Vue korzysta z sesyjnego trybu Sanctuma, który akceptuje
> tylko domeny z `SANCTUM_STATEFUL_DOMAINS` (w `.env` ustawione na localhost i 127.0.0.1,
> port 8000). Przy `php artisan serve --port=INNY` trzeba dopisać ten port, inaczej
> każde żądanie z widgetu skończy się kodem 401.

```bash
# --- Zadanie 3 -----------------------------------------------------------
cd zadanie-3-task-queue
npm test          # 10 testów
npm run examples  # 3 przykłady użycia
```

## Testy

```bash
cd laravel-notes && php artisan test
```

```
Tests:    49 passed (188 assertions)
```

| Plik | Zakres |
| --- | --- |
| `tests/Feature/Api/NoteApiTest.php` | pełny CRUD, izolacja danych, walidacja 422, paginacja, limit notatek, 401 bez logowania |
| `tests/Feature/Api/AuthTest.php` | rejestracja, logowanie, token Sanctum, brak enumeracji kont, wylogowanie |
| `tests/Feature/Api/NotificationApiTest.php` | lista, licznik nieprzeczytanych, oznaczanie (idempotentne), izolacja |
| `tests/Feature/NoteCreatedNotificationTest.php` | zdarzenie → kolejkowany listener → Mailable (Zadanie 5b) |
| `tests/Unit/NoteServiceTest.php` | reguły biznesowe bez bazy, na atrapie repozytorium (Zadanie 2) |
| `tests/Unit/NotePolicyTest.php` | polityka bezpośrednio i przez `Gate` |

Trzy testy wymagane treścią Zadania 1 to `tworzy_notatke_dla_zalogowanego_uzytkownika`,
`zwraca_liste_wlasnych_notatek_z_paginacja_po_15` oraz
`proba_dostepu_do_cudzej_notatki_konczy_sie_404`.

---

## Zadanie 1 — REST API z uwierzytelnianiem

### Endpointy

| Metoda | Ścieżka | Opis |
| --- | --- | --- |
| `POST` | `/api/register` | rejestracja, zwraca token (limit 6 żądań/min) |
| `POST` | `/api/login` | logowanie, zwraca token (limit 6 żądań/min) |
| `POST` | `/api/logout` | unieważnia token użyty w żądaniu |
| `GET` | `/api/user` | dane zalogowanego użytkownika |
| `GET` | `/api/notes` | lista, paginacja 15/stronę (`?page=`, `?per_page=` max 50) |
| `POST` | `/api/notes` | nowa notatka → `201` + nagłówek `Location` |
| `GET` | `/api/notes/{id}` | pojedyncza notatka |
| `PUT`/`PATCH` | `/api/notes/{id}` | aktualizacja, także częściowa |
| `DELETE` | `/api/notes/{id}` | usunięcie → `204` |

### Kształt odpowiedzi

```json
{
  "data": [ { "id": 31, "title": "…", "content": "…", "is_pinned": true,
              "created_at": "2026-07-23T12:38:38+00:00", "updated_at": "…" } ],
  "meta": { "current_page": 1, "per_page": 15, "last_page": 2, "total": 24,
            "pinned_total": 4, "from": 1, "to": 15, "path": "…", "links": [ … ] },
  "links": { "first": "…", "last": "…", "prev": null, "next": null }
}
```

`pinned_total` to **globalny** licznik przypiętych notatek użytkownika, nie tylko
z bieżącej strony — bez niego liczniki w nagłówku widgetu (Zadanie 4) byłyby błędne
przy więcej niż jednej stronie wyników.

### Kody odpowiedzi

| Kod | Kiedy |
| --- | --- |
| `422` | błąd walidacji (`errors` z komunikatami per pole) lub reguły biznesowej (tylko `message`) |
| `401` | brak lub nieważny token / sesja |
| `404` | notatka nie istnieje **albo należy do kogoś innego** |
| `419` | brak nagłówka CSRF w trybie sesyjnym |

**Dlaczego cudza notatka daje 404, nie 403.** Repozytorium zawęża każde zapytanie do
właściciela, więc obcy zasób po prostu nie istnieje z perspektywy żądania. To
mocniejsze niż 403: nie potwierdzamy nawet istnienia notatki o danym ID. `NotePolicy`
(`viewAny`, `view`, `create`, `update`, `delete`) działa jako druga warstwa, na już
wczytanym modelu, i jest przetestowana osobno.

### Ustalanie właściciela

`user_id` nigdy nie pochodzi z ciała żądania — ustawia go serwis na podstawie
uwierzytelnionego użytkownika. Jest na to test
(`tworzenie_ignoruje_probe_podstawienia_wlasciciela`): wysłanie `user_id` obcego
użytkownika w POST nie ma żadnego efektu.

### Dwa tryby uwierzytelniania

Sanctum obsługuje oba i oba prowadzą do tych samych endpointów `/api/*`:

- **token osobisty** — dla klientów API (`Authorization: Bearer …`), używany w testach;
- **sesja cookie** — dla widgetu Vue na tym samym origin (`statefulApi()` w
  `bootstrap/app.php`). Token nie leży w `localStorage`, ciasteczko jest `HttpOnly`,
  a operacje zapisujące są chronione CSRF.

---

## Zadanie 4 — widget Vue w widoku Blade

Aplikacja **nie jest SPA**. Laravel renderuje widoki, Vue montuje się w `#app`
i przejmuje tylko widget. Routing, sesja i uprawnienia zostają po stronie Laravela.

```
resources/js/app.js                    rejestracja komponentów + konfiguracja axios
resources/js/components/NoteManager.vue lista, liczniki, filtrowanie, polling, paginacja
resources/js/components/NoteForm.vue    formularz tworzenia i edycji, obsługa 422
resources/views/notes.blade.php         <note-manager></note-manager>
resources/views/layouts/app.blade.php   #app, navbar z dzwonkiem
```

Zrealizowane wymagania: lista pobierana w `mounted()`, odświeżanie co 3 minuty
(`setInterval`, czyszczone w `beforeUnmount`), filtrowanie po tytule przez `computed`
(bez dodatkowego zapytania), optymistyczny toggle `is_pinned` z rollbackiem w `.catch()`,
skeleton loader, dwa różne stany puste („brak notatek” vs „filtr nic nie znalazł”),
usuwanie przez `confirm()`, liczniki w nagłówku karty, `watch` na propie `note`
w `NoteForm`, komunikacja props w dół / `emit` w górę (`saved`, `cancel`).

### Dwie rzeczy, które warto znać

**`resolve.alias` na build Vue z kompilatorem.** Domyślny import `vue` w bundlerze to
wersja runtime-only, która nie umie skompilować szablonu wziętego z DOM. Ponieważ
komponent osadzamy znacznikiem `<note-manager></note-manager>` wprost w Bladzie, Vue musi
ten HTML skompilować w przeglądarce. Bez aliasu w `vite.config.js` `#app` renderuje się
jako pusty `<!---->` i widget **w ogóle się nie pojawia** — bez żadnego błędu w konsoli.

**`withXSRFToken` to osobna flaga.** W axios 1.x samo `withCredentials = true` nie
wystarcza; bez `withXSRFToken = true` każdy `POST/PUT/PATCH/DELETE` z widgetu kończy się
kodem 419.

### Paginacja w widgecie

API zwraca 15 notatek na stronę, więc widget ma sterowanie stronami (szkielet z zadania
go nie przewidywał, ale bez tego 9 z 24 notatek byłoby niedostępnych). Filtrowanie
działa — zgodnie z wymaganiem — na już pobranej stronie, bez dodatkowego zapytania;
stan pusty wprost o tym informuje („Żadna notatka **na tej stronie**…”).

---

## Zadanie 5a — dzwonek powiadomień

| Metoda | Ścieżka | Opis |
| --- | --- | --- |
| `GET` | `/api/notifications` | 20 najnowszych + `meta.unread_count` (licznik globalny) |
| `PATCH` | `/api/notifications/{id}/read` | oznacza jedno jako przeczytane (idempotentnie) |
| `PATCH` | `/api/notifications/read-all` | oznacza wszystkie, zwraca liczbę zmienionych |

Trasa `read-all` jest zdefiniowana **przed** trasą z parametrem — inaczej „read-all”
zostałoby dopasowane jako `{notification}`.

Komponent: badge z liczbą nieprzeczytanych (`99+` powyżej 99), polling co 60 s,
optymistyczne oznaczanie z rollbackiem, skeleton loader, stan pusty, zamykanie panelu
kliknięciem poza nim, czas względny liczony ręcznie — z poprawną polską odmianą
(`1 minutę` / `2 minuty` / `5 minut`), bo `Intl.RelativeTimeFormat` nie pokrywa formy
„2–4”, a `day.js` byłby zależnością dla jednej funkcji.

Model `Notification` nie korzysta z systemu notyfikacji Laravela — specyfikacja wymaga
jawnych kolumn `type`/`title`/`body`/`read_at`, a nie UUID i JSON-owego `data`. Dlatego
`User` **nie** używa traitu `Notifiable`: jego relacja `notifications()` kolidowałaby
z naszą. Wyjaśnienie jest w komentarzu w modelu.

---

## Świadome decyzje i ograniczenia

- **Bootstrap zamiast Tailwinda.** Szkielet Laravela 13 przychodzi z Tailwindem, ale
  zadanie wymaga Bootstrapowego skeleton loadera i ikon `bi-bell` — więc Tailwind został
  wymieniony na Bootstrap 5 + Bootstrap Icons.
- **`Model::preventLazyLoading()` poza produkcją.** Zapytania N+1 są traktowane jako
  błąd, a nie jako coś do znalezienia kiedyś na produkcji.
- **Notatki nie mają soft delete.** `DELETE` usuwa trwale, bo tego wymaga specyfikacja.
- **Limit notatek nie jest transakcyjny** — szczegóły i rozwiązanie w
  [`docs/zadanie-2-refaktoryzacja.md`](laravel-notes/docs/zadanie-2-refaktoryzacja.md).
- **Powiadomienia nie mają repozytorium.** Model dostępu jest trywialny (jedna tabela,
  trzy zapytania), więc `NotificationService` rozmawia z Eloquentem bezpośrednio.
  Dodanie interfejsu byłoby warstwą bez treści. Zasada „kontroler nie dotyka modeli”
  jest zachowana.
- **Brak weryfikacji e-maila i resetu hasła.** Poza zakresem zadań.
