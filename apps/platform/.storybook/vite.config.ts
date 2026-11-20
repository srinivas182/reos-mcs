import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

/** Storybook's own Vite config: React + Tailwind, without the Laravel plugins. */
export default defineConfig({
    plugins: [react(), tailwindcss()],
    // Don't copy Laravel's public/ (index.php, build output) into the static Storybook.
    publicDir: false,
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('../resources/js', import.meta.url)),
        },
    },
});
