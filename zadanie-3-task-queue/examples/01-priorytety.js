/**
 * Przyklad 1: priorytety i limit rownoleglosci.
 *
 * 6 zadan, pula 2 wykonawcow. Widac, ze kolejnosc startu wynika z priorytetu,
 * przy rownym priorytecie dziala FIFO, a naraz pracuja maksymalnie 2 zadania.
 *
 * node examples/01-priorytety.js
 */
import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const queue = new TaskQueue();

// Liczymy realna liczbe jednoczesnie pracujacych zadan, zeby sprawdzic limit.
let running = 0;
let maxRunning = 0;
const startOrder = [];

const makeTask = (name) => async () => {
    running += 1;
    maxRunning = Math.max(maxRunning, running);
    startOrder.push(name);

    await sleep(50);

    running -= 1;

    return name;
};

// Dodajemy w kolejnosci losowej wzgledem priorytetu.
queue.add(makeTask('niski-A'), 1, 'niski-A');
queue.add(makeTask('krytyczny'), 100, 'krytyczny');
queue.add(makeTask('niski-B'), 1, 'niski-B'); // ten sam priorytet co niski-A, wiec pojdzie po nim
queue.add(makeTask('sredni'), 50, 'sredni');
queue.add(makeTask('domyslny'), 0, 'domyslny');
queue.add(makeTask('wysoki'), 90, 'wysoki');

console.log('Statystyki przed run():', queue.getStats());

const results = await queue.run(2);

// krytyczny -> wysoki -> sredni -> niski-A -> niski-B -> domyslny
console.log('Kolejność startu:  ', startOrder.join(' -> '));
console.log('Maks. równolegle:  ', maxRunning, '(limit 2)');
console.log('Statystyki po run():', queue.getStats());
console.log('Wyniki:            ', results.map((r) => `${r.label}=${r.status}`).join(', '));
