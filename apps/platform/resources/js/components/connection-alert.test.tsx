import { render as baseRender, screen } from '@testing-library/react';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it } from 'vitest';
import { ConnectionAlert } from '@/components/connection-alert';
import { i18n } from '@/lib/i18n';
import { timeAgo } from '@/lib/time-ago';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const now = new Date('2026-11-21T12:00:00Z');
const alert = {
    id: 7,
    integration: 'google-workspace',
    name: 'Google Calendar and Gmail',
    account: 'james.miller@example.com',
    since: new Date(Date.now() - 2 * 86_400_000).toISOString(),
};

afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('ConnectionAlert', () => {
    it('says what stopped, since when, and offers the 30-second fix', () => {
        render(<ConnectionAlert alert={alert} />);

        expect(
            screen.getByRole('heading', {
                name: 'Google Calendar and Gmail stopped syncing',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /^2 days ago\. Your Google Calendar and Gmail data/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Reconnect · 30 seconds' }),
        ).toHaveAttribute('href', '/connections/google-workspace/connect');
        expect(screen.getByText('GC')).toBeInTheDocument();
    });

    it('speaks Spanish', async () => {
        await i18n.changeLanguage('es');
        render(<ConnectionAlert alert={alert} />);

        expect(
            screen.getByText(/^Anteayer\. Es posible que sus datos/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Volver a conectar · 30 segundos',
            }),
        ).toBeInTheDocument();
    });
});

describe('timeAgo', () => {
    it('picks the largest whole unit, in the reader’s language', () => {
        expect(timeAgo('2026-11-19T12:00:00Z', 'en', now)).toBe('2 days ago');
        expect(timeAgo('2026-11-20T12:00:00Z', 'en', now)).toBe('Yesterday');
        expect(timeAgo('2026-11-21T09:00:00Z', 'en', now)).toBe('3 hours ago');
        expect(timeAgo('2026-11-21T11:59:30Z', 'es', now)).toBe('Ahora');
    });
});
