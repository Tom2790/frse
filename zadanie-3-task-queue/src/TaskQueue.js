/**
 * Kolejka zadan asynchronicznych z priorytetami i limitem rownoleglosci.
 *
 * @typedef {Object} TaskResult
 * @property {'fulfilled'|'rejected'} status
 * @property {string} label
 * @property {number} priority
 * @property {*} [value]   tylko dla fulfilled
 * @property {*} [reason]  tylko dla rejected
 */
class TaskQueue {
    #queue = [];

    #stats = { pending: 0, running: 0, completed: 0, failed: 0 };

    // Numer wstawienia. Sluzy tylko do etykiety zadania.
    #sequence = 0;

    #logger;

    #concurrency = 1;

    // Uchwyt aktywnego run(). null = kolejka nie pracuje.
    #runPromise = null;

    #resolveRun = null;

    #results = [];

    #pumpScheduled = false;

    /**
     * @param {Object} [options]
     * @param {Object} [options.logger=console] Obiekt z metoda error(). Wstrzykiwany, zeby testy
     *                                         mogly sprawdzic logowanie bez brudzenia konsoli.
     */
    constructor({ logger = console } = {}) {
        if (typeof logger?.error !== 'function') {
            throw new TypeError('TaskQueue: logger musi mieć metodę error().');
        }

        this.#logger = logger;
    }

    /**
     * Dodaje zadanie do kolejki.
     *
     * @param {() => Promise<*>|*} fn Funkcja zwracajaca Promise. Synchroniczna tez przejdzie.
     * @param {number} [priority=0] Wyzsza wartosc = wczesniejsze wykonanie.
     * @param {string} [label] Etykieta do logow i wynikow.
     * @returns {Promise<*>} Wynik tego jednego zadania.
     */
    add(fn, priority = 0, label = null) {
        if (typeof fn !== 'function') {
            throw new TypeError('TaskQueue.add(): fn musi być funkcją.');
        }

        if (!Number.isFinite(priority)) {
            throw new TypeError('TaskQueue.add(): priority musi być liczbą.');
        }

        const sequence = this.#sequence++;
        const task = {
            fn,
            priority,
            label: label ?? `task#${sequence + 1}`,
            resolve: null,
            reject: null,
        };

        const handle = new Promise((resolve, reject) => {
            task.resolve = resolve;
            task.reject = reject;
        });

        this.#insert(task);
        this.#stats.pending++;

        if (this.#runPromise !== null) {
            this.#schedule();
        }

        // Uchwyt jest opcjonalny. Bez tego catch zignorowanie go dawaloby
        // unhandledRejection, mimo ze blad jest juz zalogowany i w raporcie run().
        handle.catch(() => {});

        return handle;
    }

    /**
     * Uruchamia kolejke. Wywolanie w trakcie pracy zwraca ten sam uchwyt,
     * zeby nie zwielokrotnic puli wykonawcow.
     *
     * @param {number} [concurrency=3]
     * @returns {Promise<TaskResult[]>} Wyniki w kolejnosci zakonczenia zadan.
     */
    async run(concurrency = 3) {
        if (!Number.isInteger(concurrency) || concurrency < 1) {
            throw new RangeError('TaskQueue.run(): concurrency musi być całkowite i >= 1.');
        }

        if (this.#runPromise !== null) {
            return this.#runPromise;
        }

        this.#concurrency = concurrency;
        this.#results = [];

        // Uchwyt trzymamy tez lokalnie, bo #pump() zeruje pole po domknieciu przebiegu.
        const runPromise = new Promise((resolve) => {
            this.#resolveRun = resolve;
        });

        this.#runPromise = runPromise;
        this.#schedule();

        return runPromise;
    }

    /** @returns {{pending: number, running: number, completed: number, failed: number}} Kopia. */
    getStats() {
        return { ...this.#stats };
    }

    /**
     * Wstawia zadanie na wlasciwa pozycje (binary search).
     * Porzadek: priorytet malejaco. Warunek >= zamiast > sprawia, ze nowe zadanie
     * laduje ZA rownymi priorytetami, czyli w obrebie priorytetu mamy FIFO.
     */
    #insert(task) {
        let low = 0;
        let high = this.#queue.length;

        while (low < high) {
            const middle = (low + high) >>> 1;

            if (this.#queue[middle].priority >= task.priority) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        this.#queue.splice(low, 0, task);
    }

    /**
     * Planuje #pump() na najblizszy microtask, raz na tick.
     *
     * Dzieki temu partia zadan dodana w jednym bloku synchronicznym jest planowana razem.
     * Gdyby add() startowal zadanie od razu, pierwsze dodane wystartowaloby niezaleznie
     * od tego, ze chwile pozniej doszlo zadanie o wyzszym priorytecie.
     */
    #schedule() {
        if (this.#pumpScheduled) {
            return;
        }

        this.#pumpScheduled = true;

        queueMicrotask(() => {
            this.#pumpScheduled = false;
            this.#pump();
        });
    }

    /** Obsadza wolne sloty, a gdy nie ma juz nic do zrobienia - domyka run(). */
    #pump() {
        while (this.#stats.running < this.#concurrency && this.#queue.length > 0) {
            this.#execute(this.#queue.shift());
        }

        const isDrained = this.#stats.running === 0 && this.#queue.length === 0;

        if (isDrained && this.#runPromise !== null) {
            const resolve = this.#resolveRun;
            const results = this.#results;

            this.#runPromise = null;
            this.#resolveRun = null;
            this.#results = [];

            resolve(results);
        }
    }

    /**
     * Wykonuje jedno zadanie. Promise.resolve().then(fn) zamiast fn() bezposrednio,
     * zeby wyjatek synchroniczny trafil w te sama galez co odrzucony Promise.
     */
    #execute(task) {
        this.#stats.pending--;
        this.#stats.running++;

        Promise.resolve()
            .then(() => task.fn())
            .then(
                (value) => {
                    this.#stats.completed++;
                    this.#results.push({
                        status: 'fulfilled',
                        label: task.label,
                        priority: task.priority,
                        value,
                    });
                    task.resolve(value);
                },
                (reason) => {
                    this.#stats.failed++;
                    this.#results.push({
                        status: 'rejected',
                        label: task.label,
                        priority: task.priority,
                        reason,
                    });
                    this.#logger.error(
                        `[TaskQueue] Zadanie "${task.label}" zakończyło się błędem:`,
                        reason instanceof Error ? reason.message : reason,
                    );
                    task.reject(reason);
                },
            )
            .finally(() => {
                this.#stats.running--;
                this.#schedule();
            });
    }
}

export default TaskQueue;
