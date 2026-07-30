/**
 * PRZYKŁAD 3 — dodawanie zadań w trakcie działania kolejki (praktyczny scenariusz)
 *
 * Scenariusz: paginowany import z API. Pierwsze zadanie pobiera stronę 1 i dopiero
 * wtedy wie, ile jest kolejnych stron — dorzuca je do już działającej kolejki.
 * Pokazujemy, że:
 *   1. `add()` wywołane w trakcie `run()` natychmiast obsadza wolne sloty
 *      (nie trzeba restartować kolejki ani czekać na kolejny `run()`),
 *   2. `run()` kończy się dopiero, gdy kolejka jest pusta i nic nie jest w toku,
 *   3. `getStats()` w trakcie pracy pokazuje bieżący stan (running / pending),
 *   4. zadanie o wysokim priorytecie dorzucone w trakcie „wyprzedza” resztę.
 *
 * Uruchomienie: node examples/03-dodawanie-w-trakcie.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const queue = new TaskQueue();
const timeline = [];

/** Pobranie jednej strony wyników. */
const fetchPage = (page) => async () => {
    timeline.push(`start strona ${page}`);
    await sleep(40);
    timeline.push(`koniec strona ${page}`);

    return { page, items: 15 };
};

/** Pierwsze zadanie: pobiera stronę 1 i planuje pozostałe strony. */
const fetchFirstPageAndSchedule = async () => {
    timeline.push('start strona 1 (rozpoznanie)');
    await sleep(40);

    const totalPages = 4; // w realnym API: odpowiedź `meta.last_page`

    // Kolejka pracuje — te zadania trafią do wolnych slotów od razu.
    for (let page = 2; page <= totalPages; page += 1) {
        queue.add(fetchPage(page), 10, `strona-${page}`);
    }

    // Zadanie serwisowe z wyższym priorytetem — wykona się przed pozostałymi stronami.
    queue.add(
        async () => {
            timeline.push('unieważnienie cache (priorytet 99)');
        },
        99,
        'cache-flush',
    );

    timeline.push(`koniec strona 1 — zaplanowano ${totalPages - 1} stron + cache-flush`);

    return { page: 1, items: 15, totalPages };
};

queue.add(fetchFirstPageAndSchedule, 10, 'strona-1');

// Podglądamy statystyki w trakcie pracy (bez czekania na koniec `run()`).
const monitor = setInterval(() => {
    const stats = queue.getStats();
    console.log(`  [monitor] running=${stats.running} pending=${stats.pending} completed=${stats.completed}`);
}, 30);

const results = await queue.run(2);
clearInterval(monitor);

console.log('\nPrzebieg:');
timeline.forEach((entry, index) => console.log(`  ${index + 1}. ${entry}`));

console.log('\nWykonanych zadań:', results.length, '(1 początkowe + 3 strony + 1 cache-flush)');
console.log('Statystyki:', queue.getStats());
// → { pending: 0, running: 0, completed: 5, failed: 0 }
