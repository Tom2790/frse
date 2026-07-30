import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';

// Osobna konfiguracja, bez laravel-vite-plugin. Ten plugin generuje manifest i hot file
// dla Laravela, co w testach jest niepotrzebne i tylko zasmieca katalog public/.
export default defineConfig({
    plugins: [vue()],
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.js'],
        globals: false,
    },
});
