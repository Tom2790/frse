/**
 * PRZYKŁAD 1 — priorytety i limit równoległości
 *
 * Scenariusz: mamy 6 zadań o różnym priorytecie i pulę 2 równoległych „wykonawców”.
 * Pokazujemy, że:
 *   1. kolejność startu wynika z priorytetu (malejąco), a nie z kolejności dodania,
 *   2. w obrębie tego samego priorytetu obowiązuje FIFO,
 *   3. w danym momencie pracują maksymalnie 2 zadania (concurrency = 2).
 *
 * Uruchomienie: node examples/01-priorytety.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const queue = new TaskQueue();

// Śledzimy faktyczną liczbę jednocześnie pracujących zadań, żeby udowodnić limit.
let running = 0;
let maxRunning = 0;
const startOrder = [];

/** Fabryka zadania: rejestruje start, „pracuje” 50 ms, zwraca nazwę. */
const makeTask = (name) => async () => {
    running += 1;
    maxRunning = Math.max(maxRunning, running);
    startOrder.push(name);

    await sleep(50);

    running -= 1;

    return name;
};

// Dodajemy w kolejności losowej względem priorytetu.
queue.add(makeTask('niski-A'), 1, 'niski-A');
queue.add(makeTask('krytyczny'), 100, 'krytyczny');
queue.add(makeTask('niski-B'), 1, 'niski-B'); // ten sam priorytet co niski-A → pójdzie po nim
queue.add(makeTask('sredni'), 50, 'sredni');
queue.add(makeTask('domyslny'), 0, 'domyslny'); // priority pominięty w opisie = 0
queue.add(makeTask('wysoki'), 90, 'wysoki');

// Przed uruchomieniem wszystkie zadania są `pending`.
console.log('Statystyki przed run():', queue.getStats());
// → { pending: 6, running: 0, completed: 0, failed: 0 }

const results = await queue.run(2);

console.log('Kolejność startu:  ', startOrder.join(' → '));
// → krytyczny → wysoki → sredni → niski-A → niski-B → domyslny
console.log('Maks. równolegle:  ', maxRunning, '(limit concurrency = 2)');
console.log('Statystyki po run():', queue.getStats());
// → { pending: 0, running: 0, completed: 6, failed: 0 }
console.log('Wyniki:            ', results.map((result) => `${result.label}=${result.status}`).join(', '));
