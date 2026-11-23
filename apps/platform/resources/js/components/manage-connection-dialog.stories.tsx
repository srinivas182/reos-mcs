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

/** James Miller's own dotloop (B9.2): his loops and his team's, both syncing. */
export const Dotloop: Story = {
    args: {
        connection: calendarConnection({
            id: 9,
            integration: 'dotloop',
            integration_name: 'dotloop',
            streams: [{ key: 'deals', direction: 'read', records: 2 }],
            calendars: null,
            profiles: [
                { id: '4711', name: 'Individual', chosen: true },
                { id: '4712', name: 'Lone Star Team', chosen: false },
            ],
        }),
    },
};
