import type { Meta, StoryObj } from '@storybook/react-vite';
import { ManageConnectionDialog } from '@/components/manage-connection-dialog';
import { calendarConnection } from '@/components/tool-fixtures';

/** "Manage" on Connections (web journey screen 19). */
const meta = {
    title: 'Connections/Manage connection',
    component: ManageConnectionDialog,
    args: { connection: calendarConnection(), onClose: () => undefined },
} satisfies Meta<typeof ManageConnectionDialog>;

export default meta;
type Story = StoryObj<typeof meta>;

/** James Miller's Google Calendar: 248 events synced, last 3 months. */
export const Calendar: Story = {};
