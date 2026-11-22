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
    attachments: Attachment[];
};

/** A file sent with a message (B6.1b): opens once virus-scanned. */
export type Attachment = {
    id: string;
    name: string;
    mime: string;
    size: number;
    image: boolean;
    status: 'pending' | 'clean' | 'infected';
    url: string | null;
};

export type BlockState = 'by_me' | 'by_them' | null;

export type AnnouncementItem = {
    id: string;
    title: string;
    body: string;
    from: string;
    author: string | null;
    sent_at: string;
    read: boolean;
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
        files: number;
        at: string;
    } | null;
    unread: number;
    blocked: BlockState;
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
    blocked: BlockState;
};

export type Contact = {
    id: number;
    name: string;
    role: string;
    organisation: string;
};
