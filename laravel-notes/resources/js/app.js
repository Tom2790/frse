/**
 * Punkt wejścia frontendu (Zadanie 4).
 *
 * Aplikacja NIE jest SPA — Laravel nadal renderuje widoki Blade, a Vue montuje się
 * punktowo w `#app` i przejmuje tylko widget notatek.
 * Routing, sesja i uprawnienia zostają po stronie Laravela.
 */
import { createApp } from 'vue';
import axios from 'axios';

import NoteManager from './components/NoteManager.vue';

/*
 * Konfiguracja axios dla Sanctuma w trybie SPA (sesja + cookie):
 *  - withCredentials  → przeglądarka dołącza ciasteczko sesji do żądań /api/*,
 *  - withXSRFToken    → axios przepisuje ciasteczko XSRF-TOKEN do nagłówka X-XSRF-TOKEN;
 *                       bez tego Laravel odrzuciłby POST/PUT/PATCH/DELETE kodem 419.
 * W axios 1.x `withXSRFToken` jest osobną flagą — samo `withCredentials` nie wystarcza.
 */
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Wygaśnięcie sesji (np. po długiej nieaktywności) kończy się serią odpowiedzi 401.
// Zamiast pokazywać użytkownikowi niezrozumiałe błędy — wracamy na ekran logowania.
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const isUnauthenticated = error.response?.status === 401;

        if (isUnauthenticated && !window.location.pathname.startsWith('/login')) {
            window.location.href = '/login';
        }

        return Promise.reject(error);
    },
);

const app = createApp({});

app.component('note-manager', NoteManager);

app.mount('#app');
