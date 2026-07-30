/**
 * PRZYKŁAD 2 — błędy nie przerywają kolejki
 *
 * Scenariusz: symulacja odpytywania kilku endpointów, z których część pada.
 * Pokazujemy, że:
 *   1. odrzucony Promise nie zatrzymuje pozostałych zadań (kolejka leci dalej),
 *   2. błąd jest logowany (logger można wstrzyknąć — tu własny, zbierający komunikaty),
 *   3. licznik `failed` rośnie, a `run()` zwraca pełny raport fulfilled/rejected,
 *   4. uchwyt z `add()` pozwala obsłużyć wynik pojedynczego zadania, ale jest opcjonalny —
 *      zignorowanie go nie wywoła `unhandledRejection`.
 *
 * Uruchomienie: node examples/02-obsluga-bledow.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Własny logger zamiast console — dokładnie ten sam mechanizm wykorzystują testy.
const logged = [];
const queue = new TaskQueue({
    logger: {
        error: (...args) => logged.push(args.join(' ')),
    },
});

/** Zadanie „udane”. */
const fetchOk = (endpoint) => async () => {
    await sleep(20);

    return { endpoint, status: 200 };
};

/** Zadanie „padające” — rzuca po krótkim czasie. */
const fetchFailing = (endpoint) => async () => {
    await sleep(20);

    throw new Error(`HTTP 500 z ${endpoint}`);
};

/** Zadanie rzucające synchronicznie — też musi zostać przechwycone. */
const brokenTask = () => {
    throw new TypeError('Zły argument — wyjątek synchroniczny');
};

queue.add(fetchOk('/api/notes'), 10, 'notes');
queue.add(fetchFailing('/api/reports'), 10, 'reports');
queue.add(fetchOk('/api/users'), 5, 'users');
queue.add(brokenTask, 5, 'broken');

// Uchwyt pojedynczego zadania: możemy zareagować lokalnie na jego błąd.
const handle = queue.add(fetchFailing('/api/legacy'), 1, 'legacy');
handle.catch((error) => console.log('Lokalna obsługa błędu zadania "legacy":', error.message));

const results = await queue.run(2);

console.log('\nRaport z run():');
for (const result of results) {
    const detail = result.status === 'fulfilled'
        ? JSON.stringify(result.value)
        : result.reason.message;

    console.log(`  ${result.label.padEnd(8)} ${result.status.padEnd(9)} ${detail}`);
}

console.log('\nStatystyki:', queue.getStats());
// → { pending: 0, running: 0, completed: 2, failed: 3 } — kolejka dobiegła do końca
console.log('Zalogowane błędy:', logged.length);
logged.forEach((entry) => console.log('  •', entry));
