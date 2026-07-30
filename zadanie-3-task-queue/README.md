# Zadanie 3 - kolejka asynchroniczna z priorytetami

Czysty JavaScript (ESM), bez zależności. Node 18+.

```
src/TaskQueue.js                    implementacja
examples/01-priorytety.js           priorytety + limit równoległości
examples/02-obsluga-bledow.js       błędy nie przerywają kolejki
examples/03-dodawanie-w-trakcie.js  dodawanie zadań podczas run()
test/TaskQueue.test.js              10 testów (node:test)
```

## Uruchomienie

```bash
npm test          # 10 testów
npm run examples  # trzy przykłady po kolei
```

## API

```js
const queue = new TaskQueue();              // opcjonalnie: new TaskQueue({ logger })

queue.add(fn, priority, label);             // zwraca Promise z wynikiem tego zadania
const results = await queue.run(3);         // limit równoległości
queue.getStats();                           // { pending, running, completed, failed }
```

`run()` zwraca tablicę wyników w kolejności zakończenia zadań:

```js
{ status: 'fulfilled', label: 'notes',   priority: 10, value: {...} }
{ status: 'rejected',  label: 'reports', priority: 10, reason: Error }
```

## Kilka rzeczy, które warto wiedzieć o implementacji

**Priorytety.** `add()` wstawia zadanie na właściwą pozycję binary searchem, więc pobranie
kolejnego to zwykły `shift()`. Warunek `>=` przy porównaniu priorytetów sprawia, że nowe
zadanie ląduje za już obecnymi o tym samym priorytecie - bez tego kolejność w obrębie
priorytetu byłaby odwrócona.

**Kolejka nie startuje zadania od razu w `add()`**, tylko planuje to na najbliższy
microtask. Dzięki temu partia zadań dodana w jednym bloku synchronicznym jest planowana
razem i priorytet działa też dla zadań dorzuconych chwilę później. Widać to w przykładzie 3:
zadanie serwisowe z priorytetem 99 wyprzedza pobieranie stron.

**Błędy.** Każde zadanie leci przez `Promise.resolve().then(fn)`, więc wyjątek
synchroniczny trafia w tę samą gałąź co odrzucony Promise. Błąd zwiększa `failed`, jest
logowany i wraca w raporcie `run()`, a kolejka obsadza slot następnym zadaniem.

**Uchwyt z `add()` jest opcjonalny** - odrzuca się przy błędzie, ale kolejka dopina do
niego wewnętrzny `.catch()`. Bez tego samo zignorowanie uchwytu wywalałoby proces na
`unhandledRejection`, mimo że błąd został już obsłużony.

**Zadania dodane w trakcie `run()`** trafiają do wolnych slotów bez restartu kolejki.
`run()` kończy się, gdy kolejka jest pusta i nic nie jest w toku, więc zadanie może
bezpiecznie zaplanować kolejne. Powtórne `run()` w trakcie pracy zwraca ten sam uchwyt,
żeby nie zwielokrotnić puli wykonawców.

## Czego nie ma

Timeoutów, ponawiania i anulowania zadań - nie ma ich w wymaganiach, a każde wymaga
osobnej decyzji (ile prób? czy ponawiać błąd walidacji? `AbortController` w kontrakcie
`fn`?). Miejsce na timeout i retry jest w `#execute()`.
