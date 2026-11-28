import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { localInput, OpenHousesView, type OpenHouse } from './open-houses-view';

const api = vi.hoisted(() => ({ getJson: vi.fn(), postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    create: '/open-houses',
    visits: (id: string) => `/open-houses/${id}/visits`,
    contact: (uuid: string) => `/contacts?contact=${uuid}`,
};
const house = (over: Partial<OpenHouse> = {}): OpenHouse => ({
    id: 'h1',
    address: '12 Willow Bend',
    city: 'Katy',
    starts_at: '2026-11-28T13:00:00-06:00',
    ends_at: '2026-11-28T16:00:00-06:00',
    open: true,
    url: 'https://reos.com/open-house/K7M2XQ9P',
    qr_url: '/open-house/K7M2XQ9P/qr.svg',
    visits: 0,
    ...over,
});

describe('Open houses (B14.3a)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('starts sign-in and shows its QR code and link', async () => {
        api.postJson.mockResolvedValue({ data: house() });
        render(<OpenHousesView houses={[]} visits={[]} routes={routes} />);
        expect(screen.getByText('No open houses yet')).toBeInTheDocument();

        await userEvent.type(
            screen.getByLabelText('Address'),
            '12 Willow Bend',
        );
        await userEvent.type(screen.getByLabelText('City'), 'Katy');
        await userEvent.click(
            screen.getByRole('button', { name: 'Start sign-in' }),
        );

        expect(api.postJson).toHaveBeenCalledWith(
            '/open-houses',
            expect.objectContaining({
                address: '12 Willow Bend',
                city: 'Katy',
            }),
        );
        expect(
            await screen.findByRole('img', {
                name: 'QR code to sign in at 12 Willow Bend',
            }),
        ).toHaveAttribute('src', '/open-house/K7M2XQ9P/qr.svg');
        expect(
            screen.getByText('Nobody has signed in yet.'),
        ).toBeInTheDocument();
    });

    it('loads the visitors of another open house, with a link to each contact', async () => {
        api.getJson.mockResolvedValue({
            data: [
                {
                    id: 'v1',
                    name: 'Rosa Lima',
                    email: 'rosa.lima@example.com',
                    phone: null,
                    has_agent: false,
                    pre_approved: true,
                    timeline: '3_months',
                    source: 'qr',
                    signed_in_at: '2026-11-21T13:20:00-06:00',
                    contact: 'c1',
                },
            ],
        });
        render(
            <OpenHousesView
                houses={[
                    house(),
                    house({
                        id: 'h0',
                        address: '88 Oak Ridge Dr',
                        open: false,
                        visits: 1,
                    }),
                ]}
                visits={[]}
                routes={routes}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: /88 Oak Ridge Dr/ }),
        );

        expect(api.getJson).toHaveBeenCalledWith('/open-houses/h0/visits');
        expect(await screen.findByText('Rosa Lima')).toBeInTheDocument();
        expect(
            screen.getByText(/In 3 months · Pre-approved/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open contact' }),
        ).toHaveAttribute('href', '/contacts?contact=c1');
    });

    it('writes datetime-local values in local time', () => {
        expect(localInput(new Date(2026, 10, 28, 13, 5))).toBe(
            '2026-11-28T13:05',
        );
    });
});
