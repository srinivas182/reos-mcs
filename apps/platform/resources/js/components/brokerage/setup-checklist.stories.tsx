import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { SetupChecklist } from './setup-checklist';

const meta = {
    title: 'Brokerage/Setup checklist',
    component: SetupChecklist,
    args: {
        setup: {
            trec_number: '0600123',
            done: 2,
            total: 6,
            steps: [
                { key: 'verify', done: true, href: '/organisation/settings' },
                { key: 'agents', done: true, href: '/agents' },
                { key: 'seats', done: false, href: '/organisation/settings' },
                {
                    key: 'manual',
                    done: false,
                    href: '/organisation/virtual-broker',
                },
                {
                    key: 'advertising',
                    done: false,
                    href: '/organisation/advertising',
                },
                { key: 'mentors', done: false, href: '/organisation/mentors' },
            ],
        },
    },
} satisfies Meta<typeof SetupChecklist>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Bayou City Homes, verified and with agents invited, four steps to go. */
export const TwoOfSix: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            await canvas.findByText(/2 of 6 done · TREC license 0600123/),
        ).toBeInTheDocument();
    },
};
