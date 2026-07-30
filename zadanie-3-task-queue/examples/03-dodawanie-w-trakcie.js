/**
 * Przyklad 3: dodawanie zadan w trakcie dzialania kolejki.
 *
 * Paginowany import: pierwsze zadanie pobiera strone 1 i dopiero wtedy wie, ile jest
 * kolejnych stron - dorzuca je do juz pracujacej kolejki. Zadanie serwisowe z wysokim
 * priorytetem wyprzedza pozostale strony. run() konczy sie, gdy nic nie zostalo.
 *
 * node examples/03-dodawanie-w-trakcie.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const queue = new TaskQueue();
const timeline = [];

const fetchPage = (page) => async () => {
    timeline.push(`start strona ${page}`);
    await sleep(40);
    timeline.push(`koniec strona ${page}`);

    return { page, items: 15 };
};

const fetchFirstPageAndSchedule = async () => {
    timeline.push('start strona 1 (rozpoznanie)');
    await sleep(40);

    const totalPages = 4; // w realnym API: meta.last_page z odpowiedzi

    for (let page = 2; page <= totalPages; page += 1) {
        queue.add(fetchPage(page), 10, `strona-${page}`);
    }

    queue.add(
        async () => {
            timeline.push('unieważnienie cache (priorytet 99)');
        },
        99,
        'cache-flush',
    );

    timeline.push(`koniec strona 1 - zaplanowano ${totalPages - 1} stron + cache-flush`);

    return { page: 1, items: 15, totalPages };
};

queue.add(fetchFirstPageAndSchedule, 10, 'strona-1');

// Podglad stanu w trakcie pracy, bez czekania na koniec run().
const monitor = setInterval(() => {
    const stats = queue.getStats();
    console.log(`  [monitor] running=${stats.running} pending=${stats.pending} completed=${stats.completed}`);
}, 30);

const results = await queue.run(2);
clearInterval(monitor);

console.log('\nPrzebieg:');
timeline.forEach((entry, index) => console.log(`  ${index + 1}. ${entry}`));

console.log('\nWykonanych zadań:', results.length, '(1 początkowe + 3 strony + cache-flush)');
console.log('Statystyki:', queue.getStats());
