import type { Meta, StoryObj } from '@storybook/react-vite';
import { RequestToolDialog } from '@/components/request-tool-dialog';

/** "Request a tool" on Connections (web journey screen 19). */
const meta = {
    title: 'Connections/Request a tool',
    component: RequestToolDialog,
    args: { open: true, onOpenChange: () => undefined },
} satisfies Meta<typeof RequestToolDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Open: Story = {};
