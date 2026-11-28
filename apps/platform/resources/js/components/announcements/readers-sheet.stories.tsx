import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { officeClosed, officeClosedReminded } from './readers-fixtures';
import { ReadersSheet } from './readers-sheet';

const meta = {
    title: 'Messaging/Who has read it',
    component: ReadersSheet,
    args: {
        open: true,
        onOpenChange: () => {},
        load: () => Promise.resolve(officeClosed),
        onRemind: () => Promise.resolve(),
    },
} satisfies Meta<typeof ReadersSheet>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Maria hasn't read "Office closed Friday" yet. */
export const OneUnread: Story = {
    play: async ({ canvasElement }) => {
        const body = within(canvasElement.ownerDocument.body);
        await expect(await body.findByText('Maria Garcia')).toBeInTheDocument();
    },
};

/** Reminded this morning: Remind waits a day. */
export const Reminded: Story = {
    args: { load: () => Promise.resolve(officeClosedReminded) },
};
