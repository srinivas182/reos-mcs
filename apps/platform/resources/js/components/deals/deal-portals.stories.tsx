import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { DealPortals } from './deal-portals';

const meta = {
    title: 'Deals/Share with your client',
    component: DealPortals,
    args: {
        parties: [
            {
                name: 'Priya Shah',
                email: 'priya.shah@example.com',
                role: 'buyer',
            },
            {
                name: 'Ravi Shah',
                email: 'ravi.shah@example.com',
                role: 'buyer',
            },
        ],
        routes: {
            list: '/storybook/no-portals',
            create: '/storybook/no-portals',
            send: (id: string) => `/storybook/portals/${id}/send`,
            revoke: (id: string) => `/storybook/portals/${id}`,
        },
    },
} satisfies Meta<typeof DealPortals>;

export default meta;

type Story = StoryObj<typeof meta>;

/** No links yet: the buyers on the deal are one tap away. */
export const FirstLink: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText('No client links yet'),
        ).toBeInTheDocument();
        await expect(
            canvas.getByRole('button', { name: 'Priya Shah' }),
        ).toBeInTheDocument();
    },
};
