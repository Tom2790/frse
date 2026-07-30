# Zadanie 2 — Repository + Service Layer

## Kod przed refaktoryzacją

Fragment z treści zadania:

```php
public function store(Request $request)
{
    $note = Note::create([
        'user_id'   => auth()->id(),
        'title'     => $request->title,
        'content'   => $request->content,
        'is_pinned' => false,
    ]);

    return response()->json($note, 201);
}
```

Co jest z tym nie tak:

1. **Brak walidacji.** `$request->title` może być `null`, tablicą albo tekstem na 10 MB.
   Puste `title` przechodzi do bazy i wywala się dopiero na `NOT NULL`, dając 500 zamiast 422.
2. **Kontroler zna Eloquenta.** Zmiana źródła danych albo dopisanie cache'u wymaga
   ruszania kontrolera. Nie da się przetestować tej logiki bez bazy.
3. **Nie ma miejsca na reguły biznesowe.** Limit notatek, wartości domyślne, zdarzenia
   domenowe — wszystko musiałoby wylądować w kontrolerze i powtórzyć się w każdym miejscu,
   które tworzy notatki (API, komenda artisan, import).
4. **Model wycieka do odpowiedzi.** `response()->json($note)` serializuje cały model,
   razem z `user_id` i tym, co ktoś kiedyś doda do tabeli. Kontrakt API zmienia się
   wtedy przy okazji migracji.
5. **`auth()->id()` w środku ciała żądania.** Zależność od globalnego stanu, przez którą
   metody nie da się wywołać z kolejki ani z CLI.
6. **`is_pinned` zahardkodowane na `false`.** Klient nie może utworzyć notatki od razu
   przypiętej.

## Kod po refaktoryzacji

```php
// app/Http/Controllers/Api/NoteController.php
public function store(StoreNoteRequest $request): JsonResponse
{
    $note = $this->notes->create(
        $request->toServicePayload(),
        $this->user($request),
    );

    return NoteResource::make($note)
        ->response()
        ->setStatusCode(Response::HTTP_CREATED)
        ->header('Location', route('api.notes.show', $note));
}
```

```php
// app/Services/NoteService.php
public function create(array $data, User $user): Note
{
    if ($this->notes->countForUser($user) >= self::MAX_NOTES_PER_USER) {
        throw new NoteLimitExceededException(self::MAX_NOTES_PER_USER);
    }

    $note = $this->notes->create([
        'title' => $data['title'],
        'content' => $data['content'],
        'is_pinned' => $data['is_pinned'] ?? false,
    ], $user);

    event(new NoteCreated($note));

    return $note;
}
```

```php
// app/Repositories/EloquentNoteRepository.php
public function create(array $data, User $user): Note
{
    $note = Note::make($data);
    $note->user()->associate($user);
    $note->save();

    return $note;
}
```

## Podział odpowiedzialności

| Warstwa | Odpowiada za | Czego NIE wie |
| --- | --- | --- |
| `StoreNoteRequest` | poprawność danych wejściowych, komunikaty błędów (422) | co się z danymi dalej stanie |
| `NoteController` | HTTP: kody odpowiedzi, nagłówki, autoryzacja politykami | jak notatki są przechowywane |
| `NoteService` | reguły biznesowe: limit 100 notatek, wartości domyślne, zdarzenia | SQL, Eloquent, HTTP |
| `EloquentNoteRepository` | zapytania, izolacja po właścicielu | reguł biznesowych |
| `NoteResource` | kształt odpowiedzi JSON | źródła danych |

## Wiązanie w kontenerze

```php
// app/Providers/AppServiceProvider.php
public array $bindings = [
    NoteRepositoryInterface::class => EloquentNoteRepository::class,
];

public function register(): void
{
    // Równoważna forma jawna (wymagana treścią zadania):
    $this->app->bind(NoteRepositoryInterface::class, EloquentNoteRepository::class);
}
```

Wystarczy jedna z tych form — właściwość `$bindings` jest tańsza, bo kontener czyta ją
bez uruchamiania metody. W repo są obie, żeby pokazać oba warianty; w realnym projekcie
zostałaby jedna.

## Dlaczego każda metoda repozytorium przyjmuje `User`

```php
public function find(int $id, User $user): ?Note;
```

Właściciel jest częścią kontraktu, nie opcjonalnym filtrem. Nie istnieje metoda, która
zwróciłaby notatki wszystkich użytkowników — więc izolacji danych **nie da się
zapomnieć** na poziomie wywołania. To mocniejsza gwarancja niż „pamiętaj dodać
`where('user_id', ...)`”.

Efekt: żądanie o cudzą notatkę kończy się `404`, a nie `403`. Świadomie — nie
potwierdzamy istnienia zasobów, których użytkownik nie powinien widzieć. `NotePolicy`
działa jako druga warstwa, na już wczytanym modelu, i ma własne testy
(`tests/Unit/NotePolicyTest.php`).

## Co ta warstwa faktycznie daje

`tests/Unit/NoteServiceTest.php` uruchamia serwis z atrapą
`InMemoryNoteRepository` — bez bazy, migracji i Eloquenta. Reguła „limit 100 notatek”
jest przetestowana w izolacji, wraz z asercją, że po przekroczeniu limitu repozytorium
**nie jest w ogóle wołane** o zapis i nie leci zdarzenie `NoteCreated`. Tego nie da się
sprawdzić na kodzie z pierwszej sekcji tego dokumentu.

## Znane ograniczenie

Sprawdzenie limitu (`countForUser`) i zapis to dwie osobne operacje, więc dwa równoległe
żądania mogą teoretycznie przepchnąć notatkę nr 101. Przy limicie „miękkim” to
akceptowalne. Gdyby limit był twardy, właściwym rozwiązaniem jest transakcja
z `lockForUpdate()` na wierszu użytkownika albo ograniczenie po stronie bazy — i jedno
i drugie zmieściłoby się w `NoteService::create()`, bez ruszania kontrolera.
