import type { Meta, StoryObj } from '@storybook/react-vite';
import { expect, within } from 'storybook/test';
import { DealBoardView } from './deal-board';
import { DealPanel } from './deal-panel';
import { DealSignatures, SendForSignatureDialog } from './deal-signatures';
import { dealBoard, elmStreet, oakCourt } from './fixtures';

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

/** B9.3: Maria's Elm St deal: an amendment out for signature and a disclosure signed and filed. */
export const Signatures: Story = {
    render: () => (
        <DealSignatures deal={elmStreet} onChanged={() => undefined} />
    ),
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await expect(
            canvas.getByText('Signed by everyone'),
        ).toBeInTheDocument();
    },
};

/** B9.3: no DocuSign yet, so the deal offers to connect it. */
export const SignaturesNotConnected: Story = {
    render: () => (
        <DealSignatures deal={oakCourt} onChanged={() => undefined} />
    ),
};

/** B9.3: Send for signature: subject, the deal's PDFs and the signers from its parties. */
export const SendForSignature: Story = {
    render: () => (
        <SendForSignatureDialog
            deal={elmStreet}
            onClose={() => undefined}
            onUploaded={() => undefined}
        />
    ),
};
