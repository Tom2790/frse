/**
 * To nie jest SPA. Laravel nadal renderuje widoki Blade, a Vue montuje sie punktowo
 * w #app i przejmuje tylko widget notatek i dzwonek. Routing, sesja i uprawnienia
 * zostaja po stronie Laravela.
 */
import { createApp } from 'vue';
import axios from 'axios';

import NoteManager from './components/NoteManager.vue';
import NotificationBell from './components/NotificationBell.vue';

/*
 * Axios dla sesyjnego trybu Sanctuma:
 *  withCredentials - przeglądarka dolacza ciasteczko sesji do zadan /api/*,
 *  withXSRFToken   - axios przepisuje ciasteczko XSRF-TOKEN do naglowka X-XSRF-TOKEN.
 *
 * W axios 1.x withXSRFToken to osobna flaga. Bez niej kazdy POST/PUT/PATCH/DELETE
 * konczy sie kodem 419.
 */
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Wygasla sesja to seria odpowiedzi 401. Zamiast pokazywac uzytkownikowi niezrozumiale
// bledy, wracamy na ekran logowania.
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
app.component('notification-bell', NotificationBell);

app.mount('#app');
