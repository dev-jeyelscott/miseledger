import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

const isDockerDevelopment = process.env.DOCKER_DEV === '1';
const dockerVitePort = Number(process.env.VITE_PORT ?? '5174');

if (
    isDockerDevelopment &&
    (!Number.isInteger(dockerVitePort) ||
        dockerVitePort < 1 ||
        dockerVitePort > 65535)
) {
    throw new Error('VITE_PORT must be a valid TCP port.');
}

export default defineConfig({
    server: isDockerDevelopment
        ? {
              host: '0.0.0.0',
              port: dockerVitePort,
              strictPort: true,
              hmr: {
                  host: process.env.VITE_HMR_HOST ?? 'localhost',
              },
              watch: {
                  usePolling: process.env.VITE_USE_POLLING === 'true',
              },
          }
        : undefined,
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
