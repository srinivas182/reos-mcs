import type { Meta, StoryObj } from '@storybook/react-vite';
import { WhatsNewCard } from '@/components/whats-new';

/** "What's new" (A6.2): the release note for a role, once, until dismissed. */
const meta = {
    title: "Shell/What's new",
    component: WhatsNewCard,
    args: {
        item: {
            id: 3,
            version: '6.2.0',
            released_on: '2026-11-22',
            note: 'Messages are here: talk to your broker and team without leaving REOS. Open Messages from the menu.',
        },
        onDismiss: () => undefined,
    },
} satisfies Meta<typeof WhatsNewCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const AgentNote: Story = {};
export const SchoolAdminNote: Story = {
    args: {
        item: {
            id: 4,
            version: '6.2.1',
            released_on: '2026-11-23',
            note: 'Announcements now show how many students have read them, for example "Read by 82 of 140".',
        },
    },
};
