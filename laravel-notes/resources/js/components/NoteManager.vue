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
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <!-- Pole filtra rosnie, ale zostawia miejsce przyciskowi obok. -->
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

            <!-- Blad komunikacji z API, inny niz 422 z formularza -->
            <div v-if="error" class="alert alert-danger d-flex justify-content-between align-items-center">
                <span>{{ error }}</span>
                <button class="btn btn-sm btn-outline-danger" type="button" @click="getNewList()">
                    Spróbuj ponownie
                </button>
            </div>

            <NoteForm
                v-if="showForm"
                :note="editNote"
                class="mb-3"
                @saved="onSaved"
                @cancel="closeForm"
            />

            <!-- Skeleton tylko przy pierwszym ladowaniu -->
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

            <!-- Rozrozniamy "brak notatek" od "filtr nic nie znalazl" -->
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

        <!-- Paginacja serwerowa, API zwraca 15 notatek na strone -->
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

// Wymog zadania: odswiezanie co 3 minuty.
const POLL_INTERVAL_MS = 3 * 60 * 1000;

export default {
    name: 'NoteManager',

    components: { NoteForm },

    data() {
        return {
            isLoading: false,      // pierwsze ladowanie, pokazuje skeleton
            isRefreshing: false,   // odswiezanie w tle, tylko dyskretny spinner
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
         * Filtrowanie bez dodatkowego zapytania, czyli na juz pobranej stronie wynikow.
         * Przy wiekszych zbiorach filtr powinien pojsc do zapytania (?search=), ale
         * zadanie wymaga tutaj computed property.
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

        this.pollTimer = setInterval(() => this.getNewList({ silent: true }), POLL_INTERVAL_MS);
    },

    beforeUnmount() {
        // Bez tego interwal zylby dalej po zniszczeniu komponentu i strzelal do API.
        clearInterval(this.pollTimer);
    },

    methods: {
        /**
         * @param {{silent?: boolean}} options silent = odswiezanie w tle, bez skeletonu
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

                    // Usuniecie ostatniej notatki na stronie moglo nas zostawic
                    // za koncem listy, wiec cofamy sie na istniejaca strone.
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
         * Optymistycznie: UI zmienia sie od razu, a gdy zadanie padnie, wracamy do
         * poprzedniego stanu. Uzytkownik nie czeka na round-trip przy operacji,
         * ktora prawie zawsze sie udaje.
         */
        togglePin(item) {
            const previous = item.is_pinned;

            item.is_pinned = !previous;
            this.countPinned += item.is_pinned ? 1 : -1;

            axios
                .patch(`/api/notes/${item.id}`, { is_pinned: item.is_pinned })
                .then(({ data }) => {
                    // Serwer jest zrodlem prawdy.
                    item.is_pinned = data.data.is_pinned;
                })
                .catch((error) => {
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

        /** @param {Object|null} note Notatka do edycji albo null dla nowej. */
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

        /** Komunikat z odpowiedzi Laravela albo fallback. */
        messageFrom(error, fallback) {
            return error.response?.data?.message ?? fallback;
        },
    },
};
</script>
