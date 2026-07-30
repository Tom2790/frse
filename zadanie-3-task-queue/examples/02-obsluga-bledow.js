/**
 * Przyklad 2: bledy nie przerywaja kolejki.
 *
 * Symulacja odpytywania kilku endpointow, z ktorych czesc pada. Odrzucony Promise
 * (a takze wyjatek synchroniczny) zwieksza licznik failed, jest logowany i wraca
 * w raporcie run(), ale pozostale zadania i tak sie wykonuja.
 *
 * node examples/02-obsluga-bledow.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Wlasny logger zamiast console - tak samo robia testy.
const logged = [];
const queue = new TaskQueue({
    logger: {
        error: (...args) => logged.push(args.join(' ')),
    },
});

const fetchOk = (endpoint) => async () => {
    await sleep(20);

    return { endpoint, status: 200 };
};

const fetchFailing = (endpoint) => async () => {
    await sleep(20);

    throw new Error(`HTTP 500 z ${endpoint}`);
};

// Rzuca synchronicznie, wiec nie zwraca nawet odrzuconego Promise'a.
const brokenTask = () => {
    throw new TypeError('Zły argument');
};

queue.add(fetchOk('/api/notes'), 10, 'notes');
queue.add(fetchFailing('/api/reports'), 10, 'reports');
queue.add(fetchOk('/api/users'), 5, 'users');
queue.add(brokenTask, 5, 'broken');

// Uchwyt z add() pozwala zareagowac na blad jednego zadania osobno.
const handle = queue.add(fetchFailing('/api/legacy'), 1, 'legacy');
handle.catch((error) => console.log('Lokalna obsługa błędu "legacy":', error.message));

const results = await queue.run(2);

console.log('\nRaport z run():');
for (const result of results) {
    const detail = result.status === 'fulfilled' ? JSON.stringify(result.value) : result.reason.message;

    console.log(`  ${result.label.padEnd(8)} ${result.status.padEnd(9)} ${detail}`);
}

// completed: 2, failed: 3 - kolejka dobiegla do konca
console.log('\nStatystyki:', queue.getStats());
console.log('Zalogowane błędy:', logged.length);
logged.forEach((entry) => console.log('  -', entry));
