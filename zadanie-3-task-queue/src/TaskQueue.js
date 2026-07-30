/**
 * TaskQueue — kolejka zadań asynchronicznych z priorytetami i limitem równoległości.
 *
 * Założenia:
 *  - `add(fn, priority)` rejestruje zadanie i zwraca uchwyt (Promise) do jego wyniku.
 *  - Wyższy `priority` = wcześniejsze wykonanie. Przy równym priorytecie obowiązuje FIFO
 *    (kolejność dodania), dzięki czemu kolejka jest stabilna i przewidywalna.
 *  - `run(concurrency)` uruchamia przetwarzanie i kończy się, gdy kolejka jest pusta
 *    i żadne zadanie nie jest już w toku.
 *  - Błąd pojedynczego zadania NIE przerywa kolejki: jest liczony w statystykach,
 *    logowany i raportowany w wyniku `run()`.
 *  - Zadania dodane w trakcie działania (np. z wnętrza innego zadania) są dociągane
 *    do wolnych slotów bez restartu kolejki.
 *
 * @typedef {Object} TaskResult
 * @property {'fulfilled'|'rejected'} status
 * @property {string} label      Etykieta zadania (własna lub auto: `task#1`).
 * @property {number} priority   Priorytet, z jakim zadanie zostało wykonane.
 * @property {*}      [value]    Wynik — tylko dla `fulfilled`.
 * @property {*}      [reason]   Błąd — tylko dla `rejected`.
 */
class TaskQueue {
    /** @type {Array<Object>} Kolejka posortowana malejąco po priorytecie. */
    #queue = [];

    #stats = { pending: 0, running: 0, completed: 0, failed: 0 };

    /** Licznik wstawień — tie-breaker gwarantujący FIFO w obrębie priorytetu. */
    #sequence = 0;

    /** Logger wstrzykiwany przez konstruktor (ułatwia testowanie). */
    #logger;

    #concurrency = 1;

    /** @type {Promise<TaskResult[]>|null} Uchwyt aktywnego przebiegu `run()`. */
    #runPromise = null;

    /** @type {((results: TaskResult[]) => void)|null} */
    #resolveRun = null;

    /** @type {TaskResult[]} Wyniki bieżącego przebiegu. */
    #results = [];

    /** Zabezpieczenie przed wielokrotnym zaplanowaniem tego samego przebiegu `#pump()`. */
    #pumpScheduled = false;

    /**
     * @param {Object}  [options]
     * @param {Object}  [options.logger=console] Obiekt z metodą `error(...)`.
     */
    constructor({ logger = console } = {}) {
        if (typeof logger?.error !== 'function') {
            throw new TypeError('TaskQueue: logger musi udostępniać metodę error().');
        }

        this.#logger = logger;
    }

    /**
     * Dodaje zadanie do kolejki.
     *
     * @param {() => Promise<*>|*} fn        Funkcja zwracająca Promise (dozwolona też synchroniczna).
     * @param {number}             [priority=0] Wyższa wartość = wyższy priorytet.
     * @param {string}             [label]   Opcjonalna etykieta widoczna w logach i wynikach.
     * @returns {Promise<*>} Uchwyt wyniku tego konkretnego zadania.
     *                       Odrzucenie tego uchwytu nie wywołuje `unhandledRejection`,
     *                       gdy wywołujący go zignoruje — kolejka sama go „odbiera”.
     */
    add(fn, priority = 0, label = null) {
        if (typeof fn !== 'function') {
            throw new TypeError('TaskQueue.add(): fn musi być funkcją zwracającą Promise.');
        }

        if (!Number.isFinite(priority)) {
            throw new TypeError('TaskQueue.add(): priority musi być liczbą skończoną.');
        }

        const sequence = this.#sequence++;
        const task = {
            fn,
            priority,
            sequence,
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

        // Kolejka już pracuje — zaplanuj obsadzenie wolnych slotów.
        if (this.#runPromise !== null) {
            this.#schedule();
        }

        // Uchwyt jest opcjonalny: gdy nikt go nie obsłuży, błąd i tak jest raportowany
        // przez logger i `run()`, więc tłumimy `unhandledRejection`.
        handle.catch(() => {});

        return handle;
    }

    /**
     * Uruchamia przetwarzanie kolejki z limitem równoległości.
     *
     * Powtórne wywołanie w trakcie działania zwraca ten sam uchwyt (bez zwielokrotniania
     * puli wykonawców), więc `run()` jest bezpieczne do wywołania z kilku miejsc.
     *
     * @param {number} [concurrency=3] Maksymalna liczba zadań wykonywanych jednocześnie.
     * @returns {Promise<TaskResult[]>} Wyniki wszystkich zadań przebiegu — w kolejności zakończenia.
     */
    async run(concurrency = 3) {
        if (!Number.isInteger(concurrency) || concurrency < 1) {
            throw new RangeError('TaskQueue.run(): concurrency musi być liczbą całkowitą >= 1.');
        }

        if (this.#runPromise !== null) {
            return this.#runPromise;
        }

        this.#concurrency = concurrency;
        this.#results = [];

        // Uchwyt trzymamy też lokalnie: `#pump()` zeruje pole po domknięciu przebiegu,
        // a metoda musi zwrócić Promise także wtedy, gdy kolejka była pusta.
        const runPromise = new Promise((resolve) => {
            this.#resolveRun = resolve;
        });

        this.#runPromise = runPromise;
        this.#schedule();

        return runPromise;
    }

    /**
     * @returns {{pending: number, running: number, completed: number, failed: number}}
     *          Kopia licznikowa — modyfikacja zwróconego obiektu nie wpływa na kolejkę.
     */
    getStats() {
        return { ...this.#stats };
    }

    /**
     * Wstawia zadanie w miejsce wynikające z priorytetu (binary search, O(log n) porównań).
     * Porządek: priorytet malejąco. Szukamy pierwszej pozycji o priorytecie *niższym*
     * (warunek `>=`), więc nowe zadanie ląduje ZA już obecnymi o tym samym priorytecie —
     * to gwarantuje FIFO w obrębie priorytetu bez porównywania `sequence`.
     */
    #insert(task) {
        let low = 0;
        let high = this.#queue.length;

        while (low < high) {
            const middle = (low + high) >>> 1;
            const goesAfter = this.#queue[middle].priority >= task.priority;

            if (goesAfter) {
                low = middle + 1;
            } else {
                high = middle;
            }
        }

        this.#queue.splice(low, 0, task);
    }

    /**
     * Planuje `#pump()` na najbliższy microtask (z deduplikacją).
     *
     * Dzięki temu partia zadań dodana w jednym bloku synchronicznym jest planowana
     * naraz — kolejka najpierw zbiera całą partię, a potem wybiera z niej zadania
     * o najwyższym priorytecie. Uruchamianie w `add()` „na gorąco” startowałoby
     * pierwsze dodane zadanie niezależnie od tego, co przyjdzie milisekundę później.
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

    /**
     * Obsadza wolne sloty zadaniami z kolejki, a gdy nie ma już nic do zrobienia —
     * domyka aktywny `run()`. Jedyne miejsce, w którym kolejka decyduje „co dalej”.
     */
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
     * Wykonuje jedno zadanie i aktualizuje statystyki. `Promise.resolve().then(fn)`
     * zapewnia, że synchroniczny wyjątek w `fn` też trafi do gałęzi błędu.
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
