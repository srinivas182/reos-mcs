import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { referralBody, ReferralsView } from './referrals-view';
import type { Referral } from './types';

const api = vi.hoisted(() => ({ postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));
// The agreement checkbox (Radix) measures itself; jsdom has no ResizeObserver.
vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    create: '/referrals',
    accept: (id: string) => `/referrals/${id}/accept`,
    decline: (id: string) => `/referrals/${id}/decline`,
    close: (id: string) => `/referrals/${id}/close`,
    contact: (uuid: string) => `/contacts?contact=${uuid}`,
};
const referral = (over: Partial<Referral> = {}): Referral => ({
    id: 'r1',
    direction: 'received',
    status: 'sent',
    kind: 'buyer',
    client: { name: 'Elena Torres', email: 'elena@example.com', phone: null },
    area: 'Austin',
    note: null,
    fee_percent: 25,
    from: { name: 'Maria Garcia', broker: 'Lone Star Realty' },
    to: {
        name: 'Dev Shah',
        email: 'dev.shah@example.com',
        broker: 'Hill Country Homes',
        on_reos: true,
    },
    contact: null,
    sent_at: '2026-11-28T10:00:00-06:00',
    answered_at: null,
    closed_at: null,
    ...over,
});

describe('Referrals (B14.3b)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('refers a client only once they agreed, with the brokers’ fee', async () => {
        api.postJson.mockResolvedValue({
            data: referral({ direction: 'sent' }),
        });
        render(
            <ReferralsView
                referrals={{ sent: [], received: [] }}
                routes={routes}
            />,
        );
        await userEvent.type(screen.getByLabelText("Agent's name"), 'Dev Shah');
        await userEvent.type(
            screen.getByLabelText("Agent's email"),
            'dev.shah@example.com',
        );
        await userEvent.type(
            screen.getByLabelText("Client's name"),
            'Elena Torres',
        );
        await userEvent.type(
            screen.getByLabelText("Client's email"),
            'elena@example.com',
        );
        const send = screen.getByRole('button', { name: 'Refer a client' });
        expect(send).toBeDisabled();

        await userEvent.click(
            screen.getByLabelText('My client agreed to be referred'),
        );
        await userEvent.click(send);
        expect(api.postJson).toHaveBeenCalledWith(
            '/referrals',
            expect.objectContaining({
                to_email: 'dev.shah@example.com',
                fee_percent: 25,
                client_phone: null,
                client_agreed: true,
            }),
        );
        expect(
            await screen.findByText('Referrals you sent'),
        ).toBeInTheDocument();
    });

    it('accepts a referral, then links to the new contact', async () => {
        api.postJson.mockResolvedValue({
            data: referral({ status: 'accepted', contact: 'c9' }),
        });
        render(
            <ReferralsView
                referrals={{ sent: [], received: [referral()] }}
                routes={routes}
            />,
        );
        expect(
            screen.getByText(
                /From Maria Garcia, Lone Star Realty · Referral fee 25%/,
            ),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Accept' }));

        expect(api.postJson).toHaveBeenCalledWith('/referrals/r1/accept');
        expect(
            await screen.findByRole('link', { name: 'Open contact' }),
        ).toHaveAttribute('href', '/contacts?contact=c9');
        expect(
            screen.queryByRole('button', { name: 'Accept' }),
        ).not.toBeInTheDocument();
    });

    it('sends blanks as null and the fee as a number', () => {
        expect(
            referralBody({
                to_name: ' Pat ',
                to_email: 'pat@example.com',
                to_brokerage: ' ',
                client_name: 'Elena',
                client_email: '',
                client_phone: '512 555 0101',
                kind: 'seller',
                area: '',
                note: '',
                fee_percent: '',
            }),
        ).toMatchObject({
            to_name: 'Pat',
            to_brokerage: null,
            client_email: null,
            area: null,
            fee_percent: null,
        });
    });
});
