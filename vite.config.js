import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

// The Vite dev server runs inside DDEV's web container. DDEV exposes it at
// https://catadvisor.ddev.site:5173 (see web_extra_exposed_ports in
// .ddev/config.yaml), so the browser has to load dev files from that address.
const ddevUrl = process.env.DDEV_PRIMARY_URL;

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: ddevUrl ? `${ddevUrl.replace(/:\d+$/, '')}:5173` : undefined,
        cors: { origin: /https?:\/\/([A-Za-z0-9\-.]+)?(\.ddev\.site)(?::\d+)?$/ },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
