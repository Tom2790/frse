# Zadanie 3 — Kolejka asynchroniczna z priorytetami

Czysty JavaScript (ES2020+, moduły ESM), zero zależności produkcyjnych i testowych.

```
src/TaskQueue.js                    # implementacja
examples/01-priorytety.js           # priorytety + limit równoległości
examples/02-obsluga-bledow.js       # błędy nie przerywają kolejki
examples/03-dodawanie-w-trakcie.js  # dodawanie zadań podczas działania run()
test/TaskQueue.test.js              # 10 testów (wbudowany runner node:test)
```

## Uruchomienie

```bash
cd zadanie-3-task-queue
npm test          # 10 testów, node --test
npm run examples  # wszystkie trzy przykłady po kolei
```

Wymagany Node 18+ (`queueMicrotask`, prywatne pola klas, `node:test`).

## API

| Metoda | Opis |
| --- | --- |
| `new TaskQueue({ logger })` | `logger` (domyślnie `console`) musi mieć metodę `error()` — wstrzykiwany, żeby testy mogły asertować logowanie. |
| `add(fn, priority = 0, label = null)` | Rejestruje zadanie. Zwraca Promise z wynikiem **tego** zadania. |
| `run(concurrency = 3)` | Uruchamia przetwarzanie. Zwraca Promise z raportem wszystkich zadań przebiegu. |
| `getStats()` | `{ pending, running, completed, failed }` — kopia, nie referencja. |

`run()` zwraca tablicę rekordów w kolejności **zakończenia**:

```js
{ status: 'fulfilled', label: 'notes', priority: 10, value: {...} }
{ status: 'rejected',  label: 'reports', priority: 10, reason: Error }
```

## Decyzje projektowe

**Priorytety przez sortowane wstawianie.** `add()` wstawia zadanie binary searchem
(O(log n) porównań) w miejsce wynikające z priorytetu, więc pobranie kolejnego zadania
to `shift()`. Alternatywą byłoby sortowanie przy każdym pobraniu — droższe i niepotrzebne.

**FIFO przy równym priorytecie.** Szukamy pierwszej pozycji o priorytecie *niższym*
(warunek `>=`), dzięki czemu nowe zadanie ląduje za już obecnymi o tym samym priorytecie.
Bez tego kolejność w obrębie priorytetu byłaby odwrócona (LIFO) — jest na to test.

**Planowanie na microtasku.** `#pump()` nie jest wołany synchronicznie z `add()`, tylko
przez `queueMicrotask` (z deduplikacją). Powód: partia zadań dodana w jednym bloku
synchronicznym ma być zaplanowana **naraz**. Gdyby kolejka startowała zadanie natychmiast
po `add()`, pierwsze dodane zadanie wystartowałoby niezależnie od tego, że milisekundę
później dorzucono zadanie o priorytecie 99. Przykład 3 pokazuje to na scenariuszu
„zadanie serwisowe wyprzedza pobieranie stron”.

**Błąd nie przerywa kolejki.** Każde zadanie jest wykonywane przez
`Promise.resolve().then(fn)`, więc wyjątek synchroniczny trafia w tę samą gałęź co
odrzucony Promise. Błąd zwiększa `failed`, jest logowany i wraca w raporcie `run()`,
a `#pump()` (wołany z `finally`) obsadza slot następnym zadaniem.

**Uchwyt z `add()` jest opcjonalny.** Zwracany Promise odrzuca się przy błędzie zadania,
ale kolejka wewnętrznie dopina do niego `.catch(() => {})`. Bez tego samo zignorowanie
uchwytu powodowałoby `unhandledRejection` i w Node ≥15 ubijało proces — mimo że błąd
został już poprawnie obsłużony i zaraportowany.

**Zadania dodane w trakcie `run()`** trafiają do wolnych slotów bez restartu kolejki
(`add()` planuje `#pump()`, gdy przebieg jest aktywny). `run()` kończy się dopiero, gdy
kolejka jest pusta **i** nic nie jest w toku — dlatego zadanie może bezpiecznie
zaplanować kolejne.

**Powtórne `run()`** w trakcie działania zwraca ten sam uchwyt, zamiast zwielokrotniać
pulę wykonawców. Dzięki temu wywołanie z dwóch miejsc nie łamie limitu równoległości.

## Testy

```
✔ wykonuje zadania w kolejności priorytetów, a przy remisie FIFO
✔ nie przekracza limitu równoległości
✔ błąd zadania nie przerywa kolejki i jest logowany
✔ przechwytuje również wyjątek synchroniczny
✔ getStats() odzwierciedla cykl życia i zwraca kopię
✔ uchwyt z add() zwraca wynik zadania i odrzuca się przy błędzie
✔ zadania dodane w trakcie run() są dociągane do wolnych slotów
✔ run() na pustej kolejce kończy się natychmiast
✔ powtórne run() w trakcie działania zwraca ten sam przebieg
✔ waliduje argumenty
```

## Czego świadomie nie ma

- **Timeoutów i ponowień** — nie ma ich w wymaganiach, a każde z nich to osobna decyzja
  projektowa (czy ponawiać przy błędzie walidacji? ile razy?). Miejsce na nie jest
  w `#execute()`.
- **Anulowania zadań** — wymagałoby `AbortController` przekazywanego do `fn`, czyli
  zmiany kontraktu funkcji zadania.
- **Priorytetowej kolejki na kopcu** — przy realnych rozmiarach kolejki (setki zadań)
  `splice()` w tablicy jest szybszy niż kopiec, bo operuje na ciągłej pamięci.
