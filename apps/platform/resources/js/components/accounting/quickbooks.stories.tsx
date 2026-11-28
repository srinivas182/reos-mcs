import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { loneStarBooks, notConnected } from './fixtures';
import { QuickBooksPanel } from './quickbooks';

const meta = {
    title: 'Accounting/QuickBooks Online',
    component: QuickBooksPanel,
    args: {
        board: loneStarBooks,
        routes: { connect: '#', accounts: '#', export: '#', disconnect: '#' },
        onChanged: () => undefined,
        onRedirect: () => undefined,
    },
} satisfies Meta<typeof QuickBooksPanel>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Lone Star Realty: connected, mapped, one sheet and one payout waiting. */
export const Connected: Story = {
    play: async ({ canvasElement }) => {
        await expect(
            within(canvasElement).getByRole('button', { name: 'Export now' }),
        ).toBeEnabled();
    },
};

/** Not connected yet. */
export const NotConnected: Story = { args: { board: notConnected } };

/** Connected, nothing waiting. */
export const UpToDate: Story = {
    args: {
        board: { ...loneStarBooks, pending: { split_sheets: [], payouts: [] } },
    },
};
