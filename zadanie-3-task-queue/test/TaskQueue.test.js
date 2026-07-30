// Testy TaskQueue. Runner wbudowany w Node, zero zaleznosci: npm test
import test from 'node:test';
import assert from 'node:assert/strict';

import TaskQueue from '../src/TaskQueue.js';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Logger zbierajacy komunikaty, zeby sprawdzic logowanie bez brudzenia konsoli.
const createSpyLogger = () => {
    const entries = [];

    return { entries, error: (...args) => entries.push(args.join(' ')) };
};

test('wykonuje zadania w kolejności priorytetów, a przy remisie FIFO', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });
    const order = [];

    queue.add(async () => order.push('a'), 1);
    queue.add(async () => order.push('b'), 10);
    queue.add(async () => order.push('c'), 1);
    queue.add(async () => order.push('d'), 5);

    await queue.run(1);

    assert.deepEqual(order, ['b', 'd', 'a', 'c']);
});

test('nie przekracza limitu równoległości', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });
    let running = 0;
    let maxRunning = 0;

    for (let index = 0; index < 10; index += 1) {
        queue.add(async () => {
            running += 1;
            maxRunning = Math.max(maxRunning, running);
            await sleep(10);
            running -= 1;
        });
    }

    await queue.run(3);

    assert.equal(maxRunning, 3);
    assert.equal(queue.getStats().completed, 10);
});

test('błąd zadania nie przerywa kolejki i jest logowany', async () => {
    const logger = createSpyLogger();
    const queue = new TaskQueue({ logger });
    const done = [];

    queue.add(async () => done.push('pierwsze'), 3);
    queue.add(async () => {
        throw new Error('bum');
    }, 2);
    queue.add(async () => done.push('trzecie'), 1);

    const results = await queue.run(1);

    assert.deepEqual(done, ['pierwsze', 'trzecie'], 'zadania po bledzie musza sie wykonac');
    assert.deepEqual(queue.getStats(), { pending: 0, running: 0, completed: 2, failed: 1 });
    assert.equal(results.filter((result) => result.status === 'rejected').length, 1);
    assert.equal(logger.entries.length, 1);
    assert.match(logger.entries[0], /bum/);
});

test('przechwytuje również wyjątek synchroniczny', async () => {
    const logger = createSpyLogger();
    const queue = new TaskQueue({ logger });

    queue.add(() => {
        throw new TypeError('sync');
    });

    const results = await queue.run(2);

    assert.equal(results[0].status, 'rejected');
    assert.ok(results[0].reason instanceof TypeError);
    assert.equal(queue.getStats().failed, 1);
});

test('getStats() odzwierciedla cykl życia i zwraca kopię', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });

    queue.add(async () => sleep(30));
    queue.add(async () => sleep(30));

    assert.deepEqual(queue.getStats(), { pending: 2, running: 0, completed: 0, failed: 0 });

    const snapshot = queue.getStats();
    snapshot.pending = 999; // mutacja kopii nie moze wplynac na kolejke

    assert.equal(queue.getStats().pending, 2);

    const runPromise = queue.run(2);
    await sleep(10);

    assert.deepEqual(queue.getStats(), { pending: 0, running: 2, completed: 0, failed: 0 });

    await runPromise;

    assert.deepEqual(queue.getStats(), { pending: 0, running: 0, completed: 2, failed: 0 });
});

test('uchwyt z add() zwraca wynik zadania i odrzuca się przy błędzie', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });

    const okHandle = queue.add(async () => 42);
    const failHandle = queue.add(async () => {
        throw new Error('nie wyszło');
    });

    await queue.run(2);

    assert.equal(await okHandle, 42);
    await assert.rejects(failHandle, /nie wyszło/);
});

test('zadania dodane w trakcie run() są dociągane do wolnych slotów', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });
    const order = [];

    queue.add(async () => {
        order.push('rodzic');
        await sleep(10);
        queue.add(async () => order.push('dziecko-niskie'), 1);
        queue.add(async () => order.push('dziecko-wysokie'), 50);
    }, 10);

    await queue.run(2);

    assert.deepEqual(order, ['rodzic', 'dziecko-wysokie', 'dziecko-niskie']);
    assert.equal(queue.getStats().completed, 3);
});

test('run() na pustej kolejce kończy się natychmiast', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });

    assert.deepEqual(await queue.run(3), []);
});

test('powtórne run() w trakcie działania zwraca ten sam przebieg', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });
    let executions = 0;

    queue.add(async () => {
        executions += 1;
        await sleep(20);
    });

    const first = queue.run(2);
    const second = queue.run(2);

    const [firstResults, secondResults] = await Promise.all([first, second]);

    assert.equal(executions, 1, 'zadanie nie moze zostac uruchomione dwukrotnie');
    assert.equal(firstResults, secondResults, 'oba wywolania dostaja ten sam uchwyt');
});

test('waliduje argumenty', async () => {
    const queue = new TaskQueue({ logger: createSpyLogger() });

    assert.throws(() => queue.add('to nie funkcja'), TypeError);
    assert.throws(() => queue.add(async () => 1, Number.NaN), TypeError);
    await assert.rejects(() => queue.run(0), RangeError);
    await assert.rejects(() => queue.run(1.5), RangeError);
    assert.throws(() => new TaskQueue({ logger: {} }), TypeError);
});
