import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: process.env.DOCKER_ENV === 'true' ? '0.0.0.0' : 'localhost',
        port: 5173,
        strictPort: true,
        hmr: process.env.DOCKER_ENV === 'true'
            ? {
                host: process.env.VITE_HMR_HOST || 'localhost',
                port: 5173,
            }
            : undefined,
        watch: {
            usePolling: process.env.DOCKER_ENV === 'true',
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
