import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        // Wsparcie dla plików .vue (Zadanie 4). Widget jest montowany punktowo
        // w widoku Blade, nie zastępuje routingu Laravela.
        vue(),
    ],
    resolve: {
        alias: {
            /*
             * Build Vue z kompilatorem szablonów.
             *
             * Domyślny import `vue` w bundlerze to wersja runtime-only, która NIE umie
             * skompilować szablonu wziętego z DOM. Ponieważ komponent osadzamy znacznikiem
             * `<note-manager></note-manager>` wprost w Bladzie, Vue musi ten fragment HTML
             * skompilować w przeglądarce — bez tego aliasu `#app` renderuje się jako pusty
             * `<!---->` i widget w ogóle się nie pojawia.
             *
             * Koszt: ~40 kB gzip więcej w bundlu. Alternatywą byłoby montowanie każdego
             * komponentu osobno przez `createApp(NoteManager)`, ale wtedy w Bladzie nie
             * dałoby się już używać własnych znaczników komponentów.
             */
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
