import type { Decorator, Preview } from '@storybook/react-vite';
import { useEffect } from 'react';
import { I18nextProvider } from 'react-i18next';
import '../resources/css/app.css';
import { TooltipProvider } from '../resources/js/components/ui/tooltip';
import { i18n } from '../resources/js/lib/i18n';

type Theme = 'light' | 'dark' | 'contrast';
type TextSize = 'standard' | 'large' | 'largest';
type Locale = 'en' | 'es';

/** Applies the toolbar's theme, text size and language exactly as the app does on <html>. */
const withAppearance: Decorator = (Story, context) => {
    const theme = context.globals.theme as Theme;
    const textSize = context.globals.textSize as TextSize;
    const locale = context.globals.locale as Locale;

    useEffect(() => {
        document.documentElement.lang = locale;
        void i18n.changeLanguage(locale);
    }, [locale]);

    useEffect(() => {
        const html = document.documentElement;
        html.classList.toggle('dark', theme === 'dark');
        html.dataset.theme = theme;
        html.dataset.textSize = textSize;
    }, [theme, textSize]);

    return (
        <I18nextProvider i18n={i18n}>
            <TooltipProvider delayDuration={0}>
                <div className="bg-background p-6 text-foreground">
                    <Story />
                </div>
            </TooltipProvider>
        </I18nextProvider>
    );
};

const preview: Preview = {
    decorators: [withAppearance],
    globalTypes: {
        locale: {
            description: 'Language',
            toolbar: {
                title: 'Language',
                icon: 'globe',
                items: [
                    { value: 'en', title: 'English' },
                    { value: 'es', title: 'Español' },
                ],
                dynamicTitle: true,
            },
        },
        theme: {
            description: 'Colour theme',
            toolbar: {
                title: 'Theme',
                icon: 'contrast',
                items: [
                    { value: 'light', title: 'Light' },
                    { value: 'dark', title: 'Dark' },
                    { value: 'contrast', title: 'Calm high contrast' },
                ],
                dynamicTitle: true,
            },
        },
        textSize: {
            description: 'Text size',
            toolbar: {
                title: 'Text size',
                icon: 'zoom',
                items: [
                    { value: 'standard', title: 'Standard' },
                    { value: 'large', title: 'Large' },
                    { value: 'largest', title: 'Largest' },
                ],
                dynamicTitle: true,
            },
        },
    },
    initialGlobals: { theme: 'light', textSize: 'standard', locale: 'en' },
    parameters: {
        layout: 'fullscreen',
        // Any accessibility violation fails the story's test (CI blocks the merge).
        a11y: { test: 'error' },
        controls: { matchers: { color: /(background|color)$/i } },
        backgrounds: { disable: true },
    },
};

export default preview;
