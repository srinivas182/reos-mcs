import { router } from '@inertiajs/react';
import {
    Ban,
    CornerUpLeft,
    Flag,
    Paperclip,
    Pencil,
    ShieldOff,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { AttachmentList } from '@/components/messages/attachment-list';
import { ReportDialog } from '@/components/messages/report-dialog';
import type {
    ConversationView,
    MessageItem,
} from '@/components/messages/types';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { block, destroy, send, unblock, update } from '@/routes/messages';

const time = (iso: string, language: string) =>
    new Date(iso).toLocaleString(language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

/** One message bubble: yours on the right; reply quote, edited, deleted; Reply, Edit and Delete. */
function Bubble({
    message,
    mine,
    read,
    onReply,
    onEdit,
}: {
    message: MessageItem;
    mine: boolean;
    read: boolean;
    onReply: (message: MessageItem) => void;
    onEdit: (message: MessageItem) => void;
}) {
    const { t, i18n } = useTranslation();
    const changeable =
        mine &&
        message.changeable_until !== null &&
        new Date(message.changeable_until) > new Date();
    const sender = message.sender?.name ?? t('messages.formerMember');

    return (
        <li
            className={cn(
                'flex flex-col gap-1',
                mine ? 'items-end' : 'items-start',
            )}
        >
            {!mine ? (
                <span className="px-1 text-sm text-muted-foreground">
                    {sender}
                </span>
            ) : null}
            <div
                className={cn(
                    'max-w-[85%] space-y-2 rounded-reos-lg px-4 py-3 text-body',
                    message.deleted
                        ? 'border border-dashed border-border bg-transparent text-muted-foreground italic'
                        : mine
                          ? 'bg-primary text-primary-foreground'
                          : 'bg-muted text-foreground',
                )}
            >
                {message.reply_to ? (
                    <p
                        className={cn(
                            'rounded-reos-md border-l-4 px-3 py-1 text-sm',
                            mine
                                ? 'border-primary-foreground/60 bg-primary-foreground/10'
                                : 'border-border bg-background',
                        )}
                    >
                        <span className="block font-semibold">
                            {message.reply_to.sender ??
                                t('messages.formerMember')}
                        </span>
                        {message.reply_to.deleted
                            ? t('messages.deleted')
                            : message.reply_to.body}
                    </p>
                ) : null}
                {message.deleted || message.body ? (
                    <p className="break-words whitespace-pre-wrap">
                        {message.deleted ? t('messages.deleted') : message.body}
                    </p>
                ) : null}
                <AttachmentList attachments={message.attachments} mine={mine} />
            </div>
            <span className="flex flex-wrap items-center gap-x-3 px-1 text-sm text-muted-foreground">
                <time dateTime={message.created_at}>
                    {time(message.created_at, i18n.language)}
                </time>
                {message.edited && !message.deleted ? (
                    <span>{t('messages.edited')}</span>
                ) : null}
                {mine && read && !message.deleted ? (
                    <span>{t('messages.read')}</span>
                ) : null}
                {!message.deleted ? (
                    <button
                        type="button"
                        onClick={() => onReply(message)}
                        className="inline-flex min-h-12 items-center gap-1 underline-offset-4 hover:underline"
                        aria-label={t('messages.replyTo', { name: sender })}
                    >
                        <CornerUpLeft aria-hidden className="size-4" />
                        {t('messages.reply')}
                    </button>
                ) : null}
                {changeable ? (
                    <>
                        <button
                            type="button"
                            onClick={() => onEdit(message)}
                            className="inline-flex min-h-12 items-center gap-1 underline-offset-4 hover:underline"
                        >
                            <Pencil aria-hidden className="size-4" />
                            {t('messages.edit')}
                        </button>
                        <button
                            type="button"
                            onClick={() =>
                                router.delete(destroy(message.id).url, {
                                    preserveScroll: true,
                                })
                            }
                            className="inline-flex min-h-12 items-center gap-1 underline-offset-4 hover:underline"
                        >
                            <Trash2 aria-hidden className="size-4" />
                            {t('messages.deleteForEveryone')}
                        </button>
                    </>
                ) : null}
            </span>
        </li>
    );
}

/**
 * The open conversation (B6.1): messages oldest first with older pages on
 * request, "Read" under your messages, and the composer with reply and
 * edit. Enter sends; Shift+Enter starts a new line.
 */
export function ConversationPanel({
    conversation,
    me,
    onOlder,
    loadingOlder = false,
}: {
    conversation: ConversationView;
    me: number;
    onOlder?: () => void;
    loadingOlder?: boolean;
}) {
    const { t } = useTranslation();
    const [body, setBody] = useState('');
    const [replyTo, setReplyTo] = useState<MessageItem | null>(null);
    const [editing, setEditing] = useState<MessageItem | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const [files, setFiles] = useState<File[]>([]);
    const [reporting, setReporting] = useState(false);
    const picker = useRef<HTMLInputElement>(null);
    const end = useRef<HTMLDivElement>(null);
    const other =
        conversation.kind === 'direct' ? conversation.people[0] : undefined;
    const blocked = conversation.blocked !== null;
    const last = conversation.messages.at(-1)?.id;

    useEffect(() => {
        end.current?.scrollIntoView?.({ block: 'end' });
    }, [last]);

    // "Read" when everyone else has read past it.
    const readBy = (message: MessageItem) =>
        conversation.reads.length > 0 &&
        conversation.reads.every(
            (r) => r.read_at !== null && r.read_at >= message.created_at,
        );

    const submit = () => {
        const text = body.trim();

        if (text === '' && (editing || files.length === 0)) {
            return;
        }

        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: (errors: Record<string, string>) =>
                setError(
                    errors.body ??
                        errors.files ??
                        Object.entries(errors).find(([key]) =>
                            key.startsWith('files.'),
                        )?.[1] ??
                        t('messages.errors.send'),
                ),
            onSuccess: () => {
                setBody('');
                setFiles([]);
                setReplyTo(null);
                setEditing(null);
                setError(null);
            },
        };

        if (editing) {
            router.patch(update(editing.id).url, { body: text }, options);
        } else {
            router.post(
                send(conversation.id).url,
                {
                    body: text,
                    reply_to: replyTo?.id ?? null,
                    ...(files.length > 0 ? { files } : {}),
                },
                { ...options, forceFormData: files.length > 0 },
            );
        }
    };

    return (
        <section
            aria-labelledby="conversation-title"
            className="flex h-full min-h-[32rem] flex-col"
        >
            <header className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-5 py-4">
                <div>
                    <h2
                        id="conversation-title"
                        className="text-lg font-semibold"
                    >
                        {conversation.title}
                    </h2>
                    {conversation.kind === 'group' ? (
                        <p className="text-sm text-muted-foreground">
                            {t('messages.people', {
                                count: conversation.people.length + 1,
                            })}
                        </p>
                    ) : null}
                </div>
                <div className="flex flex-wrap gap-2">
                    {other ? (
                        conversation.blocked === 'by_me' ? (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.delete(unblock(other.id).url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <ShieldOff aria-hidden />
                                {t('messages.unblock')}
                            </Button>
                        ) : (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        block().url,
                                        { user: other.id },
                                        { preserveScroll: true },
                                    )
                                }
                                aria-label={t('messages.blockPerson', {
                                    name: other.name,
                                })}
                            >
                                <Ban aria-hidden />
                                {t('messages.block')}
                            </Button>
                        )
                    ) : null}
                    <Button
                        variant="outline"
                        onClick={() => setReporting(true)}
                    >
                        <Flag aria-hidden />
                        {t('messages.report.action')}
                    </Button>
                </div>
            </header>
            {blocked ? (
                <p
                    role="status"
                    className="bg-reos-tone-warning-bg px-5 py-3 text-body text-reos-tone-warning-fg"
                >
                    {conversation.blocked === 'by_me'
                        ? t('messages.blockedByMe', { name: other?.name ?? '' })
                        : t('messages.blockedByThem')}
                </p>
            ) : null}
            <ReportDialog
                open={reporting}
                conversation={conversation.id}
                people={conversation.people}
                group={conversation.kind === 'group'}
                onOpenChange={setReporting}
            />

            <div
                className="flex-1 space-y-4 overflow-y-auto px-5 py-4"
                aria-live="polite"
                aria-relevant="additions"
            >
                {conversation.older && onOlder ? (
                    <div className="text-center">
                        <Button
                            variant="outline"
                            onClick={onOlder}
                            disabled={loadingOlder}
                        >
                            {t('messages.older')}
                        </Button>
                    </div>
                ) : null}
                <ul className="space-y-4">
                    {conversation.messages.map((message) => (
                        <Bubble
                            key={message.id}
                            message={message}
                            mine={message.sender?.id === me}
                            read={readBy(message)}
                            onReply={(m) => {
                                setEditing(null);
                                setReplyTo(m);
                            }}
                            onEdit={(m) => {
                                setReplyTo(null);
                                setEditing(m);
                                setBody(m.body ?? '');
                            }}
                        />
                    ))}
                </ul>
                <div ref={end} />
            </div>

            <form
                className="space-y-2 border-t border-border px-5 py-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    submit();
                }}
            >
                {replyTo || editing ? (
                    <p className="flex items-center justify-between gap-3 rounded-reos-md bg-muted px-3 py-2 text-sm">
                        <span className="truncate">
                            {editing
                                ? t('messages.editing')
                                : t('messages.replyingTo', {
                                      name:
                                          replyTo?.sender?.name ??
                                          t('messages.formerMember'),
                                  })}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label={t('messages.cancel')}
                            onClick={() => {
                                setReplyTo(null);
                                if (editing) {
                                    setEditing(null);
                                    setBody('');
                                }
                            }}
                        >
                            <X aria-hidden />
                        </Button>
                    </p>
                ) : null}
                <label htmlFor="message-body" className="sr-only">
                    {t('messages.write')}
                </label>
                {files.length > 0 ? (
                    <ul
                        className="flex flex-wrap gap-2"
                        aria-label={t('messages.files.chosen')}
                    >
                        {files.map((file, index) => (
                            <li
                                key={`${file.name}-${index}`}
                                className="flex items-center gap-1 rounded-reos-md bg-muted py-1 pr-1 pl-3 text-sm"
                            >
                                {file.name}
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    aria-label={t('messages.files.remove', {
                                        name: file.name,
                                    })}
                                    onClick={() =>
                                        setFiles(
                                            files.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <X aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ul>
                ) : null}
                <input
                    ref={picker}
                    type="file"
                    multiple
                    accept="image/*,application/pdf"
                    className="sr-only"
                    tabIndex={-1}
                    aria-hidden
                    onChange={(e) => {
                        setFiles(
                            [
                                ...files,
                                ...Array.from(e.target.files ?? []),
                            ].slice(0, 5),
                        );
                        e.target.value = '';
                    }}
                />
                <div className="flex items-end gap-3">
                    {!editing ? (
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="size-12"
                            aria-label={t('messages.files.add')}
                            disabled={blocked}
                            onClick={() => picker.current?.click()}
                        >
                            <Paperclip aria-hidden />
                        </Button>
                    ) : null}
                    <Textarea
                        id="message-body"
                        rows={2}
                        value={body}
                        maxLength={4000}
                        placeholder={t('messages.writePlaceholder')}
                        onChange={(e) => setBody(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                submit();
                            }
                        }}
                        className="min-h-12 flex-1"
                    />
                    <Button
                        type="submit"
                        disabled={
                            busy ||
                            blocked ||
                            (body.trim() === '' &&
                                (editing !== null || files.length === 0))
                        }
                    >
                        {editing ? t('messages.save') : t('messages.send')}
                    </Button>
                </div>
                <InputError message={error ?? undefined} />
            </form>
        </section>
    );
}
