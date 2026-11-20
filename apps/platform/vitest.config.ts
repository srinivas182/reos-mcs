import { storybookTest } from '@storybook/addon-vitest/vitest-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import { playwright } from '@vitest/browser-playwright';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const alias = {
    '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
};

/**
 * Two test projects, kept separate from vite.config.ts so tests don't load the
 * Laravel plugins:
 *
 * - `components`: fast jsdom tests with Testing Library (`*.test.tsx`).
 * - `storybook`: every story rendered in real Chromium and checked with axe;
 *   an accessibility violation fails the run.
 *
 *   pnpm test             → both
 *   pnpm test:components  → jsdom only (pre-push)
 *   pnpm test:stories     → stories only
 */
export default defineConfig({
    test: {
        projects: [
            {
                plugins: [react()],
                resolve: { alias },
                test: {
                    name: 'components',
                    environment: 'jsdom',
                    include: ['resources/js/**/*.test.{ts,tsx}'],
                    setupFiles: ['resources/js/__tests__/setup.ts'],
                    css: false,
                },
            },
            {
                plugins: [
                    react(),
                    tailwindcss(),
                    storybookTest({
                        configDir: fileURLToPath(
                            new URL('./.storybook', import.meta.url),
                        ),
                    }),
                ],
                resolve: { alias },
                test: {
                    name: 'storybook',
                    browser: {
                        enabled: true,
                        headless: true,
                        provider: playwright(),
                        instances: [{ browser: 'chromium' }],
                    },
                },
            },
        ],
    },
});
