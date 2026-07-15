import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // Bindt op 0.0.0.0 zodat de host-browser Vite kan bereiken vanaf
        // buiten de container, maar `origin` zorgt dat de URL's die Laravel
        // in `public/hot` schrijft naar `localhost` verwijzen — anders geeft
        // de browser een net-fout op `0.0.0.0:5173` en missen alle CSS/JS.
        host: '0.0.0.0',
        origin: 'http://localhost:5173',
        // Vite ≥5 stuurt standaard alleen zijn eigen dev-origin in
        // Access-Control-Allow-Origin. De app draait op :8000 (nginx), dus
        // zonder deze allowlist blokkeert de browser alle assets van :5173
        // met een CORS-fout — resultaat: geen Trix, geen Alpine-plugins.
        cors: {
            origin: [
                'http://localhost:8000',
                'http://127.0.0.1:8000',
            ],
        },
        hmr: {
            host: 'localhost',
        },
    },
});
