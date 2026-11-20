import type { Meta, StoryObj } from '@storybook/react-vite';
import { useState } from 'react';
import { Toaster } from 'sonner';
import { expect, userEvent, within } from 'storybook/test';
import { Button } from '@/components/ui/button';
import { undoToast } from '@/lib/undo-toast';

/**
 * Do the action straight away and offer Undo, instead of "Are you sure?".
 * Keep confirmation dialogs only for things that can't be undone.
 */
const meta = {
    title: 'Guidance/Undo toast',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

export const ArchiveWithUndo: Story = {
    name: 'Archive with undo',
    render: function Render() {
        const [archived, setArchived] = useState(false);

        return (
            <div className="space-y-4">
                <p className="text-foreground">
                    Maria Gomez · {archived ? 'Archived' : 'Active client'}
                </p>
                <Button
                    variant="outline"
                    disabled={archived}
                    onClick={() => {
                        setArchived(true);
                        undoToast({
                            message: 'Contact archived',
                            onUndo: () => setArchived(false),
                            undoneMessage: 'Contact restored',
                        });
                    }}
                >
                    Archive contact
                </Button>
                <Toaster position="bottom-right" />
            </div>
        );
    },
    play: async ({ canvasElement }) => {
        const canvas = within(canvasElement);
        await userEvent.click(
            canvas.getByRole('button', { name: 'Archive contact' }),
        );
        await expect(canvas.getByText(/Archived/)).toBeInTheDocument();
        const body = within(canvasElement.ownerDocument.body);
        await userEvent.click(
            await body.findByRole('button', { name: 'Undo' }),
        );
        await expect(
            await canvas.findByText(/Active client/),
        ).toBeInTheDocument();
    },
};
