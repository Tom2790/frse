# Zadanie 2 - Repository + Service Layer

## Kod z treści zadania

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

Co tu nie działa:

1. **Brak walidacji.** `$request->title` może być `null`, tablicą albo tekstem na 10 MB.
   Puste `title` przechodzi dalej i wywala się na `NOT NULL` w bazie, czyli klient dostaje
   500 zamiast 422.
2. **Kontroler zna Eloquenta.** Zmiana źródła danych albo dopisanie cache'u wymaga ruszania
   kontrolera, a tej logiki nie da się przetestować bez bazy.
3. **Nie ma gdzie wsadzić reguł biznesowych.** Limit notatek, wartości domyślne, zdarzenia -
   wszystko wylądowałoby w kontrolerze i powtórzyło się w każdym miejscu tworzącym notatki
   (API, komenda artisan, import).
4. **Model wycieka do odpowiedzi.** `response()->json($note)` serializuje cały model razem
   z `user_id` i tym, co ktoś kiedyś dopisze do tabeli. Kontrakt API zmienia się wtedy
   przy okazji migracji.
5. **`auth()->id()` w środku metody** to zależność od globalnego stanu. Tego kodu nie da się
   wywołać z kolejki ani z CLI.
6. **`is_pinned` na sztywno `false`** - nie da się od razu utworzyć notatki przypiętej.

## Po refaktoryzacji

```php
// NoteController
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
// NoteService
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
// EloquentNoteRepository
public function create(array $data, User $user): Note
{
    $note = Note::make($data);
    $note->user()->associate($user);
    $note->save();

    return $note;
}
```

## Kto za co odpowiada

| Warstwa | Odpowiada za | Czego nie wie |
| --- | --- | --- |
| `StoreNoteRequest` | poprawność wejścia, komunikaty 422 | co się z danymi stanie dalej |
| `NoteController` | kody HTTP, nagłówki, autoryzacja politykami | jak notatki są przechowywane |
| `NoteService` | limit 100 notatek, wartości domyślne, zdarzenia | SQL, Eloquent, HTTP |
| `EloquentNoteRepository` | zapytania, zawężenie do właściciela | reguł biznesowych |
| `NoteResource` | kształt JSON-a | źródła danych |

## Binding

```php
// AppServiceProvider
public array $bindings = [
    NoteRepositoryInterface::class => EloquentNoteRepository::class,
];

public function register(): void
{
    // Ta sama rzecz zapisana jawnie, tak jak wymaga treść zadania.
    $this->app->bind(NoteRepositoryInterface::class, EloquentNoteRepository::class);
}
```

Wystarczy jedna z tych form - właściwość `$bindings` jest tańsza, bo kontener czyta ją bez
uruchamiania metody. W repo są obie, bo zadanie wprost prosi o `$this->app->bind()`.
W realnym projekcie zostałaby jedna.

## Dlaczego każda metoda repozytorium bierze `User`

```php
public function find(int $id, User $user): ?Note;
```

Właściciel jest częścią kontraktu, a nie opcjonalnym filtrem. Nie ma tu metody, która
zwróciłaby notatki wszystkich, więc o izolacji danych nie da się zapomnieć w miejscu
wywołania. To mocniejsza gwarancja niż „pamiętaj dodać `where('user_id', ...)`".

Skutek: żądanie o cudzą notatkę kończy się kodem 404, nie 403. Tak ma być - nie
potwierdzamy istnienia zasobów, których użytkownik nie powinien widzieć.

## Co ta warstwa faktycznie daje

`tests/Unit/NoteServiceTest.php` uruchamia serwis z atrapą `InMemoryNoteRepository`, więc
bez bazy, migracji i Eloquenta. Limit 100 notatek jest przetestowany w izolacji, razem
z asercją, że po jego przekroczeniu repozytorium nie jest w ogóle wołane o zapis i nie
leci zdarzenie `NoteCreated`. Na kodzie z pierwszej sekcji tego pliku nie da się tego
sprawdzić.

## Znane ograniczenie

Sprawdzenie limitu i zapis to dwie osobne operacje, więc dwa równoległe żądania mogą
teoretycznie przepchnąć notatkę numer 101. Przy limicie miękkim jest to akceptowalne.
Gdyby miał być twardy, właściwym rozwiązaniem jest transakcja z `lockForUpdate()` na
wierszu użytkownika albo ograniczenie po stronie bazy - jedno i drugie zmieściłoby się
w `NoteService::create()`, bez ruszania kontrolera.
