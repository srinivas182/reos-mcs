import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { FarmingView, statTiles } from './farming-view';
import type { FarmArea, FarmDraft } from './types';

const api = vi.hoisted(() => ({
    getJson: vi.fn(),
    postJson: vi.fn(),
    deleteJson: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    create: '/farming',
    remove: (id: string) => `/farming/${id}`,
    draft: (id: string) => `/farming/${id}/draft`,
    send: (id: string) => `/farming/${id}/send`,
    check: '/compliance/check',
};
const area: FarmArea = {
    id: 'a1',
    name: 'Katy',
    city: 'Katy',
    postal_code: null,
    tag: 'Katy farm',
    mailing_address: '1200 Grand Pkwy, Katy, TX 77494',
    recipients: 2,
    last_sent_at: null,
};
const draft = (over: Partial<FarmDraft> = {}): FarmDraft => ({
    area,
    stats: {
        active: 4,
        pending: 1,
        new_listings: 3,
        sold: 4,
        median_price: 427000,
        median_days: 12,
        median_sold_price: null,
        months_supply: 3,
        from: '2026-08-23',
        to: '2026-11-23',
    },
    subject: 'Katy market update, November 2026',
    body: 'Here is how the Katy housing market looked.\n\nJames Miller, Linda Park Realty',
    attribution: 'Based on information from HAR for Katy.',
    check: { ok: true, issues: [] },
    can_send: true,
    next_send_at: null,
    ...over,
});

describe('Farming (B14.3c)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('opens this month’s update and sends it once it passes the check', async () => {
        api.getJson.mockResolvedValue({ data: draft() });
        api.postJson.mockResolvedValue({
            data: draft({
                can_send: false,
                next_send_at: '2026-11-30T09:00:00-06:00',
            }),
        });
        render(<FarmingView areas={[area]} tags={[]} routes={routes} />);
        await userEvent.click(
            screen.getByRole('button', { name: /Katy farm/ }),
        );

        expect(api.getJson).toHaveBeenCalledWith('/farming/a1/draft');
        expect(
            await screen.findByDisplayValue(
                'Katy market update, November 2026',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Passes the compliance check.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /Sale prices aren’t shown|Sale prices aren't shown/,
            ),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Send to 2 contacts' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/farming/a1/send', {
            subject: 'Katy market update, November 2026',
            body: expect.stringContaining('James Miller'),
        });
        expect(
            await screen.findByText(/The next update can go on/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Send to 2 contacts' }),
        ).toBeDisabled();
    });

    it('can’t send while the compliance check finds a problem', async () => {
        api.getJson.mockResolvedValue({
            data: draft({
                check: {
                    ok: false,
                    issues: [
                        {
                            code: 'broker',
                            severity: 'block',
                            phrase: 'Linda Park Realty',
                            fix: null,
                            reason: null,
                            citation: '22 TAC §535.155(c)',
                            url: '',
                            source: 'rule',
                        },
                    ],
                },
            }),
        });
        render(<FarmingView areas={[area]} tags={[]} routes={routes} />);
        await userEvent.click(
            screen.getByRole('button', { name: /Katy farm/ }),
        );

        expect(
            await screen.findByText('Fix these before sending:'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Send to 2 contacts' }),
        ).toBeDisabled();
    });

    it('shows sale prices only when the MLS allows them', () => {
        const money = (n: number) => `$${n}`;
        expect(statTiles(draft().stats, money).map((t) => t.key)).not.toContain(
            'medianSold',
        );
        expect(
            statTiles(
                { ...draft().stats, median_sold_price: 455000 },
                money,
            ).map((t) => t.key),
        ).toContain('medianSold');
    });
});
