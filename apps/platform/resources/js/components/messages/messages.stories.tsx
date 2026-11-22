import type { Meta, StoryObj } from '@storybook/react-vite';
import { ConversationList } from '@/components/messages/conversation-list';
import { ConversationPanel } from '@/components/messages/conversation-panel';
import { NewMessageDialog } from '@/components/messages/new-message-dialog';

const at = '2026-11-22T15:04:00Z';

/** Messages (B6.1): James Miller and his broker, Linda Park, at Lone Star Realty. */
const meta = {
    title: 'Messages/Conversation list',
    component: ConversationList,
    args: {
        selected: 'c-1',
        inbox: [
            {
                id: 'c-1',
                kind: 'direct',
                title: 'Linda Park',
                people: [{ id: 2, name: 'Linda Park' }],
                last_message: {
                    body: 'On it. I will send comments by 5.',
                    sender: 'Linda Park',
                    deleted: false,
                    files: 0,
                    at,
                },
                unread: 2,
                blocked: null,
                updated_at: at,
            },
            {
                id: 'c-2',
                kind: 'group',
                title: 'Friday huddle',
                people: [],
                last_message: {
                    body: 'Agenda is in the shared folder.',
                    sender: 'Ana Okoye',
                    deleted: false,
                    files: 0,
                    at,
                },
                unread: 0,
                blocked: null,
                updated_at: at,
            },
        ],
    },
} satisfies Meta<typeof ConversationList>;

export default meta;
type Story = StoryObj<typeof meta>;

export const Inbox: Story = {};
export const NoMessagesYet: Story = {
    args: { inbox: [], onNew: () => undefined },
};
export const OpenConversation: Story = {
    render: () => (
        <ConversationPanel
            me={7}
            conversation={{
                id: 'c-1',
                kind: 'direct',
                title: 'Linda Park',
                people: [{ id: 2, name: 'Linda Park' }],
                reads: [{ user: 2, read_at: '2026-11-22T15:10:00Z' }],
                older: true,
                blocked: null,
                messages: [
                    {
                        id: 'm-1',
                        body: 'Can you look at the Elm St offer before I send it?',
                        sender: { id: 7, name: 'James Miller' },
                        reply_to: null,
                        edited: false,
                        deleted: false,
                        created_at: at,
                        changeable_until: null,
                        attachments: [],
                    },
                    {
                        id: 'm-2',
                        body: 'On it. I will send comments by 5.',
                        sender: { id: 2, name: 'Linda Park' },
                        reply_to: {
                            id: 'm-1',
                            body: 'Can you look at the Elm St offer before I send it?',
                            sender: 'James Miller',
                            deleted: false,
                        },
                        edited: false,
                        deleted: false,
                        created_at: at,
                        changeable_until: null,
                        attachments: [],
                    },
                    {
                        id: 'm-3',
                        body: null,
                        sender: { id: 7, name: 'James Miller' },
                        reply_to: null,
                        edited: false,
                        deleted: true,
                        created_at: at,
                        changeable_until: null,
                        attachments: [],
                    },
                ],
            }}
        />
    ),
};
export const NewMessage: Story = {
    render: () => (
        <NewMessageDialog
            open
            onOpenChange={() => undefined}
            contacts={[
                {
                    id: 2,
                    name: 'Linda Park',
                    role: 'broker',
                    organisation: 'Lone Star Realty',
                },
                {
                    id: 3,
                    name: 'Marcus Lee',
                    role: 'mentor',
                    organisation: 'Lone Star Realty',
                },
            ]}
        />
    ),
};
