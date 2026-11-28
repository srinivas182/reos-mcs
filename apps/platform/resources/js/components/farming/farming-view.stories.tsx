import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { FarmingView } from './farming-view';

const meta = {
    title: 'Leads/Farming',
    component: FarmingView,
    args: {
        areas: [],
        tags: ['Katy farm', 'Past clients'],
        routes: {
            create: '/storybook/farming',
            remove: (id: string) => `/storybook/farming/${id}`,
            draft: (id: string) => `/storybook/farming/${id}/draft`,
            send: (id: string) => `/storybook/farming/${id}/send`,
            check: '/storybook/compliance/check',
        },
    },
} satisfies Meta<typeof FarmingView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** No areas yet: the form and a calm empty state. */
export const FirstArea: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('No farm areas yet'),
        ).toBeInTheDocument();
    },
};

/** Two areas James farms in Katy, one sent last week. */
export const TwoAreas: Story = {
    args: {
        areas: [
            {
                id: 'a1',
                name: 'Cinco Ranch',
                city: null,
                postal_code: '77494',
                tag: 'Katy farm',
                mailing_address: '1200 Grand Pkwy, Katy, TX 77494',
                recipients: 214,
                last_sent_at: '2026-11-20T10:00:00-06:00',
            },
            {
                id: 'a2',
                name: 'Old Katy',
                city: 'Katy',
                postal_code: null,
                tag: 'Old Katy',
                mailing_address: '1200 Grand Pkwy, Katy, TX 77494',
                recipients: 0,
                last_sent_at: null,
            },
        ],
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByRole('button', { name: /Cinco Ranch/ }),
        ).toBeInTheDocument();
    },
};
