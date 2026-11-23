import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { wallet } from '@/components/ce/fixtures';
import { i18n } from '@/lib/i18n';
import CeWalletPage from '@/pages/ce/index';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { reload: vi.fn(), visit: vi.fn(), on: vi.fn(() => () => {}) },
    usePage: () => ({ url: '/ce', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the CE wallet on the web (A11.2)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            const body =
                typeof init?.body === 'string'
                    ? (JSON.parse(init.body) as { renewed: boolean })
                    : { renewed: false };
            const steps = wallet().renewal.steps.map((s) =>
                s.code === 'renew' ? { ...s, done: body.renewed } : s,
            );
            const data = wallet({
                renewal: {
                    ...wallet().renewal,
                    renewed_at: body.renewed ? '2026-11-24T15:00:00Z' : null,
                    steps,
                },
            });
            return new Response(JSON.stringify({ data }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('shows who reported each completion, offers CE and records "I\'ve renewed"', async () => {
        const user = userEvent.setup();
        render(<CeWalletPage wallets={[wallet()]} />);

        expect(screen.getByText('Texas license #0798765')).toBeInTheDocument();
        expect(screen.getByText('Self-reported')).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: /Get CE courses/ }),
        ).toHaveAttribute('href', '/checkout/7');
        expect(
            screen.getByRole('link', { name: /Open: Renew with TREC/ }),
        ).toHaveAttribute(
            'href',
            'https://aca-prod.accela.com/TREC/Default.aspx',
        );

        await user.click(
            screen.getByRole('button', { name: 'I’ve renewed with TREC' }),
        );
        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: 'Undo' }),
            ).toBeInTheDocument(),
        );
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toContain('/ce/licences/1/renewal');
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toEqual({ renewed: true });
    });
});
