/** Shapes the server sends for Messages (B6.1), live and on load. */
export type MessageItem = {
    id: string;
    body: string | null;
    sender: { id: number; name: string } | null;
    reply_to: {
        id: string;
        body: string | null;
        sender: string | null;
        deleted: boolean;
    } | null;
    edited: boolean;
    deleted: boolean;
    created_at: string;
    changeable_until: string | null;
};

export type InboxItem = {
    id: string;
    kind: 'direct' | 'group';
    title: string;
    people: { id: number; name: string }[];
    last_message: {
        body: string | null;
        sender: string | null;
        deleted: boolean;
        at: string;
    } | null;
    unread: number;
    updated_at: string | null;
};

export type ConversationView = {
    id: string;
    kind: 'direct' | 'group';
    title: string;
    people: { id: number; name: string }[];
    reads: { user: number; read_at: string | null }[];
    messages: MessageItem[];
    older: boolean;
};

export type Contact = {
    id: number;
    name: string;
    role: string;
    organisation: string;
};
