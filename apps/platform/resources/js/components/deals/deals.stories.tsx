import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { DealBoardView } from './deal-board';
import { DealPanel } from './deal-panel';
import { dealBoard, oakCourt } from './fixtures';

const meta = {
    title: 'Agent/Deals',
    component: DealBoardView,
    args: {
        board: dealBoard,
        onOpen: () => undefined,
        onMove: () => undefined,
    },
} satisfies Meta<typeof DealBoardView>;

export default meta;

type Story = StoryObj<typeof meta>;

/** Screen 14: columns by stage; the Lone Wolf deal is read-only and missing a document. */
export const Board: Story = {
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(canvas.getByText('From Lone Wolf')).toBeInTheDocument();
        await expect(
            canvas.getByText('1 document missing'),
        ).toBeInTheDocument();
    },
};

/** A deal from Lone Wolf, opened: read-only, with Open in Lone Wolf. */
export const ToolDeal: Story = {
    render: () => (
        <DealPanel
            deal={oakCourt}
            onMove={() => undefined}
            onEdit={() => undefined}
            onDelete={() => undefined}
        />
    ),
};
