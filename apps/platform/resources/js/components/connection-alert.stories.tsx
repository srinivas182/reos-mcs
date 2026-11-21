import type { Meta, StoryObj } from '@storybook/react-vite';
import { ConnectionAlert } from '@/components/connection-alert';

const twoDaysAgo = new Date(Date.now() - 2 * 86_400_000).toISOString();

/** "When a sync breaks" (Connections and Sync journey, screen 26), on the web. */
const meta = {
    title: 'Connections/Connection alert',
    component: ConnectionAlert,
    parameters: { layout: 'padded' },
    args: {
        alert: {
            id: 7,
            integration: 'google-calendar',
            name: 'Google Calendar',
            account: 'james.miller@lonestarrealty.example',
            since: twoDaysAgo,
        },
    },
} satisfies Meta<typeof ConnectionAlert>;

export default meta;
type Story = StoryObj<typeof meta>;

/** James Miller's calendar stopped syncing two days ago. */
export const StoppedSyncing: Story = {};
