import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Poorten per stack (zie docker-compose.yml): de standaardstack draait op
// 8000 (nginx) en 5173 (Vite); een extra feature-stack krijgt eigen poorten
// via RZVG_WEB_PORT / RZVG_VITE_PORT.
const webPort = process.env.RZVG_WEB_PORT || '8000';
const vitePort = Number(process.env.RZVG_VITE_PORT || 5173);

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
        port: vitePort,
        strictPort: true,
        origin: `http://localhost:${vitePort}`,
        // Vite ≥5 stuurt standaard alleen zijn eigen dev-origin in
        // Access-Control-Allow-Origin. De app draait op de web-poort (nginx),
        // dus zonder deze allowlist blokkeert de browser alle assets van Vite
        // met een CORS-fout — resultaat: geen Trix, geen Alpine-plugins.
        cors: {
            origin: [
                `http://localhost:${webPort}`,
                `http://127.0.0.1:${webPort}`,
            ],
        },
        hmr: {
            host: 'localhost',
            port: vitePort,
        },
    },
});
