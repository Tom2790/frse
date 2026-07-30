<template>
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span class="fw-semibold">
                Notatki ({{ count }} | Przypięte: {{ countPinned }})
            </span>

            <span v-if="isRefreshing" class="text-body-secondary small">
                <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>
                Odświeżanie…
            </span>
        </div>

        <div class="card-body">
            <!-- Pasek narzędzi: filtrowanie + dodawanie -->
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <!-- `flex: 1 1 18rem` — pole filtra rośnie, ale zostawia miejsce
                     przyciskowi obok; zawija się dopiero na wąskich ekranach. -->
                <div class="input-group" style="flex: 1 1 18rem; max-width: 34rem">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input
                        v-model="search"
                        type="search"
                        class="form-control"
                        placeholder="Filtruj po tytule…"
                        aria-label="Filtruj notatki po tytule"
                    >
                    <button
                        v-if="search"
                        class="btn btn-outline-secondary"
                        type="button"
                        @click="search = ''"
                    >
                        Wyczyść
                    </button>
                </div>

                <button class="btn btn-primary" type="button" @click="openForm(null)">
                    <i class="bi bi-plus-lg me-1"></i>Dodaj notatkę
                </button>
            </div>

            <!-- Błąd komunikacji z API (inny niż 422 z formularza) -->
            <div v-if="error" class="alert alert-danger d-flex justify-content-between align-items-center">
                <span>{{ error }}</span>
                <button class="btn btn-sm btn-outline-danger" type="button" @click="getNewList()">
                    Spróbuj ponownie
                </button>
            </div>

            <!-- Formularz tworzenia / edycji -->
            <NoteForm
                v-if="showForm"
                :note="editNote"
                class="mb-3"
                @saved="onSaved"
                @cancel="closeForm"
            />

            <!-- Skeleton loader na pierwszym ładowaniu -->
            <div v-if="isLoading" class="table-responsive" aria-busy="true">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 3rem"></th>
                            <th scope="col">Tytuł</th>
                            <th scope="col">Utworzono</th>
                            <th scope="col" style="width: 8rem"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in 5" :key="`skeleton-${row}`">
                            <td><div class="skeleton-line" style="width: 1.2rem"></div></td>
                            <td><div class="skeleton-line" style="width: 70%"></div></td>
                            <td><div class="skeleton-line" style="width: 6rem"></div></td>
                            <td><div class="skeleton-line" style="width: 5rem"></div></td>
                        </tr>
                    </tbody>
                </table>
                <span class="visually-hidden">Ładowanie notatek…</span>
            </div>

            <!-- Stan pusty: rozróżniamy „brak notatek” od „filtr nic nie znalazł” -->
            <div v-else-if="filteredList.length === 0" class="text-center text-body-secondary py-5">
                <i class="bi bi-journal-text fs-1 d-block mb-2"></i>

                <template v-if="note_list.length === 0">
                    <p class="mb-2">Nie masz jeszcze żadnych notatek.</p>
                    <button class="btn btn-sm btn-primary" type="button" @click="openForm(null)">
                        Dodaj pierwszą notatkę
                    </button>
                </template>

                <template v-else>
                    <p class="mb-2">Żadna notatka na tej stronie nie pasuje do „{{ search }}”.</p>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="search = ''">
                        Wyczyść filtr
                    </button>
                </template>
            </div>

            <!-- Lista notatek -->
            <div v-else class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 3rem">
                                <span class="visually-hidden">Przypięta</span>
                            </th>
                            <th scope="col">Tytuł</th>
                            <th scope="col" style="width: 10rem">Utworzono</th>
                            <th scope="col" style="width: 8rem">
                                <span class="visually-hidden">Akcje</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in filteredList" :key="item.id">
                            <td>
                                <button
                                    class="btn btn-link p-0 border-0"
                                    type="button"
                                    :title="item.is_pinned ? 'Odepnij notatkę' : 'Przypnij notatkę'"
                                    :aria-pressed="item.is_pinned"
                                    @click="togglePin(item)"
                                >
                                    <i
                                        :class="item.is_pinned
                                            ? 'bi bi-pin-angle-fill text-warning'
                                            : 'bi bi-pin-angle text-secondary'"
                                    ></i>
                                </button>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ item.title }}</div>
                                <div class="text-body-secondary small">{{ excerpt(item.content) }}</div>
                            </td>
                            <td class="text-body-secondary small">{{ formatDate(item.created_at) }}</td>
                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-secondary me-1"
                                    type="button"
                                    @click="openForm(item)"
                                >
                                    <i class="bi bi-pencil"></i>
                                    <span class="visually-hidden">Edytuj</span>
                                </button>
                                <button
                                    class="btn btn-sm btn-outline-danger"
                                    type="button"
                                    :disabled="deletingId === item.id"
                                    @click="deleteNote(item.id)"
                                >
                                    <i class="bi bi-trash"></i>
                                    <span class="visually-hidden">Usuń</span>
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Paginacja serwerowa: API zwraca 15 notatek na stronę -->
        <div
            v-if="!isLoading && lastPage > 1"
            class="card-footer d-flex justify-content-between align-items-center"
        >
            <button
                class="btn btn-sm btn-outline-secondary"
                type="button"
                :disabled="page <= 1"
                @click="goToPage(page - 1)"
            >
                <i class="bi bi-chevron-left"></i> Poprzednia
            </button>

            <span class="small text-body-secondary">Strona {{ page }} z {{ lastPage }}</span>

            <button
                class="btn btn-sm btn-outline-secondary"
                type="button"
                :disabled="page >= lastPage"
                @click="goToPage(page + 1)"
            >
                Następna <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

import NoteForm from './NoteForm.vue';

/** Co ile milisekund lista odświeża się sama (wymóg: 3 minuty). */
const POLL_INTERVAL_MS = 3 * 60 * 1000;

export default {
    name: 'NoteManager',

    components: { NoteForm },

    data() {
        return {
            isLoading: false,      // pierwsze ładowanie → skeleton
            isRefreshing: false,   // odświeżanie w tle → dyskretny spinner
            note_list: [],
            search: '',
            showForm: false,
            editNote: null,
            count: 0,
            countPinned: 0,
            page: 1,
            lastPage: 1,
            error: '',
            deletingId: null,
            pollTimer: null,
        };
    },

    computed: {
        /**
         * Filtrowanie po tytule bez dodatkowego zapytania do API — działa na już
         * pobranej stronie wyników. Przy większych zbiorach filtr powinien trafić
         * do zapytania (parametr `?search=`), ale specyfikacja wymaga tutaj
         * właśnie computed property.
         */
        filteredList() {
            const needle = this.search.trim().toLowerCase();

            if (needle === '') {
                return this.note_list;
            }

            return this.note_list.filter((item) => item.title.toLowerCase().includes(needle));
        },
    },

    mounted() {
        this.isLoading = true;
        this.getNewList();

        // Automatyczne odświeżanie listy co 3 minuty.
        this.pollTimer = setInterval(() => this.getNewList({ silent: true }), POLL_INTERVAL_MS);
    },

    beforeUnmount() {
        // Bez tego interwał żyłby dalej po zniszczeniu komponentu i strzelał do API.
        clearInterval(this.pollTimer);
    },

    methods: {
        /**
         * Pobiera aktualną stronę listy.
         *
         * @param {{silent?: boolean}} options `silent` = odświeżanie w tle (bez skeletonu)
         */
        getNewList({ silent = false } = {}) {
            if (silent) {
                this.isRefreshing = true;
            }

            return axios
                .get('/api/notes', { params: { page: this.page } })
                .then(({ data }) => {
                    this.note_list = data.data;
                    this.count = data.meta.total;
                    this.countPinned = data.meta.pinned_total ?? 0;
                    this.lastPage = data.meta.last_page;
                    this.error = '';

                    // Usunięcie ostatniej notatki na stronie mogło zostawić nas
                    // za końcem listy — cofamy się na istniejącą stronę.
                    if (this.page > this.lastPage) {
                        this.page = this.lastPage;

                        return this.getNewList({ silent });
                    }
                })
                .catch((error) => {
                    this.error = this.messageFrom(error, 'Nie udało się pobrać notatek.');
                })
                .finally(() => {
                    this.isLoading = false;
                    this.isRefreshing = false;
                });
        },

        goToPage(page) {
            this.page = page;
            this.isLoading = true;
            this.getNewList();
        },

        /**
         * Optymistyczne przypięcie/odpięcie: UI zmienia się natychmiast, a gdy żądanie
         * padnie — wracamy do stanu poprzedniego i pokazujemy komunikat. Użytkownik nie
         * czeka na round-trip przy operacji, która niemal zawsze się udaje.
         */
        togglePin(item) {
            const previous = item.is_pinned;

            item.is_pinned = !previous;
            this.countPinned += item.is_pinned ? 1 : -1;

            axios
                .patch(`/api/notes/${item.id}`, { is_pinned: item.is_pinned })
                .then(({ data }) => {
                    // Serwer jest źródłem prawdy — przyjmujemy jego wersję.
                    item.is_pinned = data.data.is_pinned;
                })
                .catch((error) => {
                    // Rollback.
                    item.is_pinned = previous;
                    this.countPinned += previous ? 1 : -1;
                    this.error = this.messageFrom(error, 'Nie udało się zmienić przypięcia notatki.');
                });
        },

        deleteNote(id) {
            if (!window.confirm('Usunąć tę notatkę? Tej operacji nie można cofnąć.')) {
                return;
            }

            this.deletingId = id;

            axios
                .delete(`/api/notes/${id}`)
                .then(() => this.getNewList({ silent: true }))
                .catch((error) => {
                    this.error = this.messageFrom(error, 'Nie udało się usunąć notatki.');
                })
                .finally(() => {
                    this.deletingId = null;
                });
        },

        /**
         * @param {Object|null} note Notatka do edycji albo `null` dla nowej.
         */
        openForm(note) {
            this.editNote = note;
            this.showForm = true;
        },

        closeForm() {
            this.showForm = false;
            this.editNote = null;
        },

        onSaved() {
            this.closeForm();
            this.getNewList({ silent: true });
        },

        excerpt(content) {
            const text = (content ?? '').replace(/\s+/g, ' ').trim();

            return text.length > 90 ? `${text.slice(0, 90)}…` : text;
        },

        formatDate(value) {
            if (!value) {
                return '';
            }

            return new Date(value).toLocaleDateString('pl-PL', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
            });
        },

        /** Wyciąga komunikat z odpowiedzi Laravela, z rozsądnym fallbackiem. */
        messageFrom(error, fallback) {
            return error.response?.data?.message ?? fallback;
        },
    },
};
</script>
