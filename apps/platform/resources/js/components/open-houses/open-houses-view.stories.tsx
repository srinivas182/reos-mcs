import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { OpenHousesView } from './open-houses-view';

const qr =
    'data:image/svg+xml;utf8,' +
    encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><rect width="10" height="10" fill="#fff"/><rect x="1" y="1" width="3" height="3"/><rect x="6" y="1" width="3" height="3"/><rect x="1" y="6" width="3" height="3"/></svg>',
    );

const meta = {
    title: 'Leads/Open houses',
    component: OpenHousesView,
    args: {
        houses: [],
        visits: [],
        routes: {
            create: '/storybook/open-houses',
            visits: (id: string) => `/storybook/open-houses/${id}/visits`,
            contact: (uuid: string) => `/contacts?contact=${uuid}`,
        },
    },
} satisfies Meta<typeof OpenHousesView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Before the first open house: the start form and a calm empty state. */
export const FirstOpenHouse: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('No open houses yet'),
        ).toBeInTheDocument();
    },
};

/** Saturday's open house in Katy, with two visitors in the CRM. */
export const SaturdayInKaty: Story = {
    args: {
        houses: [
            {
                id: 'h1',
                address: '12 Willow Bend',
                city: 'Katy',
                starts_at: '2026-11-28T13:00:00-06:00',
                ends_at: '2026-11-28T16:00:00-06:00',
                open: true,
                url: 'https://reos.com/open-house/K7M2XQ9P',
                qr_url: qr,
                visits: 2,
            },
        ],
        visits: [
            {
                id: 'v1',
                name: 'Rosa Lima',
                email: 'rosa.lima@example.com',
                phone: '832 555 0177',
                has_agent: false,
                pre_approved: true,
                timeline: '3_months',
                source: 'qr',
                signed_in_at: '2026-11-28T13:20:00-06:00',
                contact: 'c1',
            },
            {
                id: 'v2',
                name: 'Ravi Patel',
                email: null,
                phone: '(713) 555-0101',
                has_agent: true,
                pre_approved: false,
                timeline: 'just_looking',
                source: 'kiosk',
                signed_in_at: '2026-11-28T13:45:00-06:00',
                contact: 'c2',
            },
        ],
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(await canvas.findByText('Rosa Lima')).toBeInTheDocument();
        await expect(
            canvas.getByRole('img', {
                name: 'QR code to sign in at 12 Willow Bend',
            }),
        ).toBeInTheDocument();
    },
};
