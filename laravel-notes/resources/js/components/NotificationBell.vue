<template>
    <div class="position-relative d-inline-block">
        <button
            type="button"
            class="btn btn-link p-0 border-0 text-decoration-none"
            :aria-expanded="panelOpen"
            :aria-label="`Powiadomienia${unreadCount > 0 ? `, nieprzeczytane: ${unreadCount}` : ''}`"
            @click.stop="togglePanel"
        >
            <i
                :class="unreadCount > 0 ? 'bi bi-bell-fill text-warning' : 'bi bi-bell text-body'"
                style="font-size: 1.3rem"
            ></i>
            <span
                v-if="unreadCount > 0"
                class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
            >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
            </span>
        </button>

        <div v-if="panelOpen" class="notification-panel card shadow text-start" @click.stop>
            <div class="card-header d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Powiadomienia</span>
                <div class="d-flex align-items-center gap-1">
                    <button
                        class="btn btn-sm btn-link text-decoration-none"
                        type="button"
                        :disabled="unreadCount === 0 || isMarkingAll"
                        @click="readAll"
                    >
                        Oznacz wszystkie
                    </button>
                    <button
                        type="button"
                        class="btn-close btn-sm"
                        aria-label="Zamknij panel powiadomień"
                        @click="panelOpen = false"
                    ></button>
                </div>
            </div>

            <div class="card-body p-0">
                <div v-if="isLoading" class="p-3" aria-busy="true">
                    <div v-for="row in 3" :key="`skeleton-${row}`" class="mb-3">
                        <div class="skeleton-line mb-2" style="width: 60%"></div>
                        <div class="skeleton-line" style="width: 90%"></div>
                    </div>
                    <span class="visually-hidden">Ładowanie powiadomień…</span>
                </div>

                <div v-else-if="error" class="p-3 text-center">
                    <p class="text-danger small mb-2">{{ error }}</p>
                    <button class="btn btn-sm btn-outline-secondary" type="button" @click="getNewList()">
                        Spróbuj ponownie
                    </button>
                </div>

                <div v-else-if="notifications.length === 0" class="p-4 text-center text-body-secondary">
                    <i class="bi bi-check2-circle fs-3 d-block mb-2"></i>
                    <span class="small">Brak powiadomień.</span>
                </div>

                <div v-else class="notification-list">
                    <div
                        v-for="item in notifications"
                        :key="item.id"
                        class="notification-item p-3"
                        :class="{ 'notification-item--unread': item.read_at === null }"
                        role="button"
                        tabindex="0"
                        @click="markAsRead(item)"
                        @keydown.enter="markAsRead(item)"
                    >
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <span class="fw-semibold small">{{ item.title }}</span>
                            <span class="text-body-secondary" style="font-size: 0.75rem; white-space: nowrap">
                                {{ timeAgo(item.created_at) }}
                            </span>
                        </div>
                        <div class="text-body-secondary small mt-1">{{ truncate(item.body, 80) }}</div>
                        <span v-if="item.read_at === null" class="visually-hidden">Nieprzeczytane</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import axios from 'axios';

const POLL_INTERVAL_MS = 60 * 1000;

export default {
    name: 'NotificationBell',

    data() {
        return {
            isLoading: false,
            isMarkingAll: false,
            panelOpen: false,
            notifications: [],
            unreadTotal: 0,
            error: '',
            pollTimer: null,
        };
    },

    computed: {
        /**
         * Licznik bierzemy z meta.unread_count, a nie z liczenia read_at === null w liscie.
         * Lista ma maksymalnie 20 pozycji, wiec przy 25 nieprzeczytanych badge pokazywalby 20.
         * Optymistyczne oznaczanie koryguje unreadTotal lokalnie, zeby badge reagowal od razu.
         */
        unreadCount() {
            return this.unreadTotal;
        },
    },

    mounted() {
        this.isLoading = true;
        this.getNewList();

        this.pollTimer = setInterval(() => this.getNewList({ silent: true }), POLL_INTERVAL_MS);

        // Klik poza panelem go zamyka. Kliki w dzwonek i w panel sa zatrzymane
        // przez @click.stop, wiec tu nie docieraja.
        document.addEventListener('click', this.closePanel);
    },

    beforeUnmount() {
        clearInterval(this.pollTimer);
        document.removeEventListener('click', this.closePanel);
    },

    methods: {
        getNewList({ silent = false } = {}) {
            return axios
                .get('/api/notifications')
                .then(({ data }) => {
                    this.notifications = data.data;
                    this.unreadTotal = data.meta?.unread_count ?? 0;
                    this.error = '';
                })
                .catch((error) => {
                    // Odswiezanie w tle nie ma psuc widoku poprawnie wczytanej listy,
                    // wiec blad pokazujemy tylko przy ladowaniu wywolanym przez uzytkownika.
                    if (!silent) {
                        this.error = error.response?.data?.message ?? 'Nie udało się pobrać powiadomień.';
                    }
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },

        togglePanel() {
            this.panelOpen = !this.panelOpen;

            if (this.panelOpen) {
                this.getNewList({ silent: true });
            }
        },

        closePanel() {
            this.panelOpen = false;
        },

        /** Optymistycznie: styl i badge zmieniaja sie od razu, przy bledzie wracamy. */
        markAsRead(item) {
            if (item.read_at !== null) {
                return;
            }

            item.read_at = new Date().toISOString();
            this.unreadTotal = Math.max(this.unreadTotal - 1, 0);

            axios
                .patch(`/api/notifications/${item.id}/read`)
                .then(({ data }) => {
                    item.read_at = data.data.read_at;
                    this.unreadTotal = data.meta?.unread_count ?? this.unreadTotal;
                })
                .catch(() => {
                    item.read_at = null;
                    this.unreadTotal += 1;
                    this.error = 'Nie udało się oznaczyć powiadomienia jako przeczytanego.';
                });
        },

        readAll() {
            // Zapamietujemy ID-ki, ktore faktycznie przelaczamy. Rollback po indeksie
            // bylby zawodny, bo polling moze podmienic liste w trakcie zadania.
            const flippedIds = this.notifications.filter((item) => item.read_at === null).map((item) => item.id);
            const previousTotal = this.unreadTotal;
            const now = new Date().toISOString();

            this.isMarkingAll = true;
            this.notifications.forEach((item) => {
                if (item.read_at === null) {
                    item.read_at = now;
                }
            });
            this.unreadTotal = 0;

            axios
                .patch('/api/notifications/read-all')
                .then(() => this.getNewList({ silent: true }))
                .catch(() => {
                    this.notifications.forEach((item) => {
                        if (flippedIds.includes(item.id)) {
                            item.read_at = null;
                        }
                    });
                    this.unreadTotal = previousTotal;
                    this.error = 'Nie udało się oznaczyć powiadomień jako przeczytanych.';
                })
                .finally(() => {
                    this.isMarkingAll = false;
                });
        },

        /**
         * Bez zewnetrznej biblioteki. Polskie formy liczby mnogiej sa nieregularne
         * (1 minute / 2 minuty / 5 minut), a Intl.RelativeTimeFormat nie zna formy 2-4.
         */
        timeAgo(dateStr) {
            if (!dateStr) {
                return '';
            }

            const seconds = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);

            if (seconds < 60) {
                return 'teraz';
            }

            const units = [
                { limit: 3600, divisor: 60, forms: ['minutę', 'minuty', 'minut'] },
                { limit: 86400, divisor: 3600, forms: ['godzinę', 'godziny', 'godzin'] },
                { limit: 2592000, divisor: 86400, forms: ['dzień', 'dni', 'dni'] },
                { limit: Infinity, divisor: 2592000, forms: ['miesiąc', 'miesiące', 'miesięcy'] },
            ];

            const unit = units.find((candidate) => seconds < candidate.limit);
            const value = Math.floor(seconds / unit.divisor);

            return `${value} ${this.pluralize(value, unit.forms)} temu`;
        },

        /** 1 -> forma 1, 2-4 -> forma 2, reszta -> forma 3. Wyjatek: 12-14 biora forme 3. */
        pluralize(value, [one, few, many]) {
            if (value === 1) {
                return one;
            }

            const lastTwo = value % 100;
            const last = value % 10;

            if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) {
                return few;
            }

            return many;
        },

        truncate(text, length) {
            const value = (text ?? '').trim();

            return value.length > length ? `${value.slice(0, length)}…` : value;
        },
    },
};
</script>
