import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

const resolveAppHost = () => {
    const appUrl = process.env.APP_URL || 'https://bengkel.test';

    try {
        return new URL(appUrl).hostname || 'bengkel.test';
    } catch {
        return 'bengkel.test';
    }
};

const appHost = resolveAppHost();
const hmrPort = 5175;
const escapedAppHost = appHost.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const appOriginPattern = new RegExp(
    `^https?:\\/\\/(?:[a-z0-9-]+\\.)*${escapedAppHost}(?::\\d+)?$`,
    'i',
);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
            detectTls: appHost,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: hmrPort,
        strictPort: true,
        cors: {
            origin: appOriginPattern,
        },
        hmr: {
            host: appHost,
            protocol: 'wss',
            port: hmrPort,
            clientPort: hmrPort,
        },
        allowedHosts: [
            appHost,
            `vite.${appHost}`,
        ],
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
