import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { MessageSquare, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { ConversationList } from '@/components/messages/conversation-list';
import { ConversationPanel } from '@/components/messages/conversation-panel';
import { NewMessageDialog } from '@/components/messages/new-message-dialog';
import { AnnouncementCards } from '@/components/messages/announcement-cards';
import type {
    AnnouncementItem,
    Contact,
    ConversationView,
    InboxItem,
    MessageItem,
} from '@/components/messages/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { realtimeEnabled } from '@/lib/realtime';
import { read as readAnnouncement } from '@/routes/announcements';
import { older as olderRoute, read } from '@/routes/messages';

/** Reloads the lists when something changes, live through Reverb (B6.1). */
function LiveUpdates({
    me,
    conversation,
}: {
    me: number;
    conversation: string | null;
}) {
    const refresh = () =>
        router.reload({ only: ['inbox', 'conversation', 'messages'] });

    useEcho(
        `users.${me}`,
        ['.message.posted', '.announcement.posted'],
        () =>
            router.reload({
                only: ['inbox', 'conversation', 'messages', 'announcements'],
            }),
        [me],
    );
    useEcho(
        `conversations.${conversation ?? 'none'}`,
        ['.message.changed', '.conversation.read'],
        refresh,
        [conversation],
    );

    return null;
}

/**
 * Messages (web, B6.1; each role's Messages entry and provider screen 26).
 * Conversations on the left, the open one on the right, New message with
 * the people you work with. Messages arrive live; opening a conversation
 * marks it read.
 */
export default function Messages({
    inbox,
    conversation,
    contacts,
    announcements = [],
}: {
    inbox: InboxItem[];
    conversation: ConversationView | null;
    contacts?: Contact[];
    announcements?: AnnouncementItem[];
}) {
    const { t } = useTranslation();
    const { messages } = usePage().props;
    const me = messages?.user ?? 0;
    const [creating, setCreating] = useState(false);
    const [earlier, setEarlier] = useState<MessageItem[]>([]);
    const [more, setMore] = useState(true);
    const [loading, setLoading] = useState(false);
    const unread =
        inbox.find((item) => item.id === conversation?.id)?.unread ?? 0;
    const lastId = conversation?.messages.at(-1)?.id;

    // Opening a conversation (or a new message arriving in it) reads it.
    useEffect(() => {
        if (conversation && unread > 0) {
            void postJson(read(conversation.id).url).then(() =>
                router.reload({ only: ['inbox', 'messages'] }),
            );
        }
    }, [conversation?.id, lastId, unread, conversation]);

    useEffect(() => {
        setEarlier([]);
        setMore(true);
    }, [conversation?.id]);

    const loadOlder = async () => {
        if (!conversation) {
            return;
        }

        const first = earlier[0]?.id ?? conversation.messages[0]?.id;
        setLoading(true);

        try {
            const response = await fetch(
                olderRoute(conversation.id, { query: { before: first ?? '' } })
                    .url,
                {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                },
            );
            const page = (await response.json()) as {
                messages: MessageItem[];
                older: boolean;
            };
            setEarlier([...page.messages, ...earlier]);
            setMore(page.older);
        } finally {
            setLoading(false);
        }
    };

    const shown = conversation
        ? {
              ...conversation,
              messages: [...earlier, ...conversation.messages],
              older: earlier.length > 0 ? more : conversation.older,
          }
        : null;

    return (
        <>
            <Head title={t('messages.title')} />
            {realtimeEnabled() && me > 0 ? (
                <LiveUpdates me={me} conversation={conversation?.id ?? null} />
            ) : null}
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('messages.title')}
                    description={t('messages.purpose')}
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('messages.new')}
                        </Button>
                    }
                />
                <AnnouncementCards
                    announcements={announcements}
                    onRead={(item) =>
                        void postJson(readAnnouncement(item.id).url).then(() =>
                            router.reload({ only: ['announcements'] }),
                        )
                    }
                />
                <div className="grid overflow-hidden rounded-reos-lg border border-border bg-card lg:grid-cols-[22rem_minmax(0,1fr)]">
                    <div className="border-b border-border lg:border-r lg:border-b-0">
                        <ConversationList
                            inbox={inbox}
                            selected={conversation?.id ?? null}
                            onNew={() => setCreating(true)}
                        />
                    </div>
                    <div>
                        {shown ? (
                            <ConversationPanel
                                key={shown.id}
                                conversation={shown}
                                me={me}
                                onOlder={() => void loadOlder()}
                                loadingOlder={loading}
                            />
                        ) : (
                            <div className="flex h-full min-h-80 items-center justify-center p-6">
                                <EmptyState
                                    icon={MessageSquare}
                                    title={t('messages.pick')}
                                    description={t('messages.pickBody')}
                                />
                            </div>
                        )}
                    </div>
                </div>
            </div>
            <NewMessageDialog
                open={creating}
                contacts={contacts}
                onOpenChange={setCreating}
            />
        </>
    );
}
