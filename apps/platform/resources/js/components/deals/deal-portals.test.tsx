import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { DealPortals, type PortalLink } from './deal-portals';

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
    list: '/deals/d1/portals',
    create: '/deals/d1/portals',
    send: (id: string) => `/deals/portals/${id}/send`,
    revoke: (id: string) => `/deals/portals/${id}`,
};
const link = (over: Partial<PortalLink> = {}): PortalLink => ({
    id: 'p1',
    name: 'Priya Shah',
    email: 'priya.shah@example.com',
    url: 'https://reos.com/portal/abc',
    opened_at: '2026-11-24T10:00:00-06:00',
    sent_at: null,
    expires_at: '2027-05-23T10:00:00-06:00',
    review: null,
    ...over,
});

describe('Share with your client (B14.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('creates a link for a party on the deal, in their language', async () => {
        api.getJson.mockResolvedValue({ data: [] });
        api.postJson.mockResolvedValue({ data: link({ opened_at: null }) });
        render(
            <DealPortals
                parties={[
                    {
                        name: 'Priya Shah',
                        email: 'priya.shah@example.com',
                        role: 'buyer',
                    },
                ]}
                routes={routes}
            />,
        );

        expect(
            await screen.findByText('No client links yet'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Priya Shah' }),
        );
        await userEvent.click(screen.getByRole('radio', { name: 'Spanish' }));
        await userEvent.click(
            screen.getByRole('button', { name: 'Create link' }),
        );

        expect(api.postJson).toHaveBeenCalledWith('/deals/d1/portals', {
            name: 'Priya Shah',
            email: 'priya.shah@example.com',
            language: 'es',
        });
        expect(await screen.findByText('Not opened yet')).toBeInTheDocument();
    });

    it('emails, shows the review, and turns a link off', async () => {
        api.getJson.mockResolvedValue({
            data: [link({ review: { rating: 5, publish: true } })],
        });
        api.postJson.mockResolvedValue({
            data: link({
                sent_at: '2026-11-25T10:00:00-06:00',
                review: { rating: 5, publish: true },
            }),
        });
        api.deleteJson.mockResolvedValue(null);
        render(<DealPortals parties={[]} routes={routes} />);

        expect(await screen.findByText('Reviewed: 5 of 5')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Email the link' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/deals/portals/p1/send');
        await userEvent.click(screen.getByRole('button', { name: 'Turn off' }));
        expect(api.deleteJson).toHaveBeenCalledWith('/deals/portals/p1');
        expect(
            await screen.findByText('No client links yet'),
        ).toBeInTheDocument();
    });
});
