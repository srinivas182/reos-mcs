import type { StorybookConfig } from '@storybook/react-vite';

/**
 * Storybook for the REOS web components: the one place designers, developers
 * and Nelle review components in every theme and text size. Every story is
 * also an automated test (render + axe accessibility), run by Vitest.
 */
const config: StorybookConfig = {
    stories: ['../resources/js/**/*.stories.@(ts|tsx)'],
    addons: [
        '@storybook/addon-docs',
        '@storybook/addon-a11y',
        '@storybook/addon-vitest',
    ],
    framework: {
        name: '@storybook/react-vite',
        options: { builder: { viteConfigPath: '.storybook/vite.config.ts' } },
    },
    core: { disableTelemetry: true },
    docs: { defaultName: 'Overview' },
};

export default config;
