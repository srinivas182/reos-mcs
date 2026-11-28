import {
    CalendarDays,
    Link as LinkIcon,
    MessageSquare,
    Pin,
    Users,
} from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { money } from '@/lib/billing';
import { deleteJson, postJson, putJson } from '@/lib/content';
import type {
    CommunityCard,
    CommunityInvite,
    CommunityRoutes,
    CommunitySession,
    CommunityView,
} from './types';

export const card =
    'space-y-4 rounded-reos-lg border border-border bg-card p-5';

export function errorText(e: unknown): string {
    return e instanceof Error ? e.message : String(e);
}

export function useWhen() {
    const { t, i18n } = useTranslation();

    return (iso: string, minutes?: number): string => {
        const date = new Date(iso).toLocaleString(i18n.language, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
        });

        return minutes === undefined
            ? date
            : t('communities.sessions.when', { date, minutes });
    };
}

/** A session's CE note: always says whether it counts. */
export function useCeNote() {
    const { t } = useTranslation();

    return (s: Pick<CommunitySession, 'ce_course'>): string =>
        s.ce_course === null
            ? t('communities.sessions.ceNone')
            : t('communities.sessions.ceCourse', { course: s.ce_course });
}

function useFormat() {
    const { t } = useTranslation();

    return (format: string): string =>
        format === 'in_person'
            ? t('communities.sessions.formats.in_person')
            : t('communities.sessions.formats.online');
}

/**
 * One expert session (B17.2): when, where, the CE note, the price, and
 * RSVP for members. The host gets cancel and the roster instead.
 */
export function SessionCard({
    session,
    isOwner,
    onRsvp,
    onCancelRsvp,
    onCancel,
    onRoster,
}: {
    session: CommunitySession;
    isOwner: boolean;
    onRsvp: () => void;
    onCancelRsvp: () => void;
    onCancel: () => void;
    onRoster: () => void;
}) {
    const { t, i18n } = useTranslation();
    const when = useWhen();
    const ce = useCeNote();
    const format = useFormat();
    const price =
        session.price_cents === 0
            ? t('communities.sessions.free')
            : money(session.price_cents, i18n.language);
    const full = session.capacity !== null && session.going >= session.capacity;
    const open = session.status === 'scheduled';

    return (
        <li className={card}>
            <div className="space-y-1">
                <p className="text-lg font-semibold">{session.title}</p>
                <p className="text-body text-muted-foreground">
                    {[
                        when(session.starts_at, session.minutes),
                        format(session.format),
                        session.place,
                        price,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>
            {session.about ? (
                <p className="text-body">{session.about}</p>
            ) : null}
            <p className="text-body font-medium">{ce(session)}</p>
            <p className="text-body text-muted-foreground">
                {session.status === 'cancelled'
                    ? t('communities.sessions.cancelled')
                    : session.status === 'done'
                      ? `${t('communities.sessions.done')} · ${t('communities.sessions.going', { count: session.attended })}`
                      : t('communities.sessions.going', {
                            count: session.going,
                        })}
            </p>
            <div className="flex flex-wrap gap-3">
                {isOwner ? (
                    <>
                        <Button variant="outline" onClick={onRoster}>
                            {t('communities.sessions.roster')}
                        </Button>
                        {open ? (
                            <Button variant="outline" onClick={onCancel}>
                                {t('communities.sessions.cancel', {
                                    title: session.title,
                                })}
                            </Button>
                        ) : null}
                    </>
                ) : session.mine === 'going' ? (
                    <>
                        <span className="inline-flex min-h-12 items-center font-medium">
                            {t('communities.sessions.going')}
                        </span>
                        {session.join_url ? (
                            <Button asChild>
                                <a
                                    href={session.join_url}
                                    rel="noreferrer"
                                    target="_blank"
                                >
                                    {t('communities.sessions.join')}
                                </a>
                            </Button>
                        ) : null}
                        {open ? (
                            <Button variant="outline" onClick={onCancelRsvp}>
                                {t('communities.sessions.cancelRsvp')}
                            </Button>
                        ) : null}
                    </>
                ) : session.mine === 'held' ? (
                    <>
                        <span className="inline-flex min-h-12 items-center">
                            {t('communities.sessions.held')}
                        </span>
                        <Button onClick={onRsvp}>
                            {t('communities.sessions.pay')}
                        </Button>
                    </>
                ) : open ? (
                    <Button onClick={onRsvp} disabled={full}>
                        {full
                            ? t('communities.sessions.full')
                            : session.price_cents === 0
                              ? t('communities.sessions.rsvp')
                              : t('communities.sessions.rsvpPaid', { price })}
                    </Button>
                ) : null}
            </div>
        </li>
    );
}

/**
 * A community (B17.2, provider screens 16–17): about, expert sessions with
 * RSVP, posts checked before they go up, and resources.
 */
export function CommunityPanel({
    community,
    routes,
    onChanged,
    onRoster = () => undefined,
}: {
    community: CommunityView;
    routes: CommunityRoutes;
    /** Something changed on the server: reload the page's data. */
    onChanged: () => void;
    onRoster?: (session: CommunitySession) => void;
}) {
    const { t } = useTranslation();
    const ids = useId();
    const [body, setBody] = useState('');
    const [title, setTitle] = useState('');
    const [url, setUrl] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<{ field: string; text: string } | null>(
        null,
    );

    const run = async (field: string, work: () => Promise<void>) => {
        setBusy(true);
        setError(null);
        try {
            await work();
            onChanged();
        } catch (e) {
            setError({ field, text: errorText(e) });
        } finally {
            setBusy(false);
        }
    };
    const errorFor = (field: string) =>
        error?.field === field ? error.text : undefined;
    const owner = community.is_owner;

    return (
        <div className="grid gap-6 xl:grid-cols-3">
            <div className="space-y-6 xl:col-span-2">
                <section
                    aria-labelledby={`${ids}-sessions`}
                    className="space-y-4"
                >
                    <h2
                        id={`${ids}-sessions`}
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <CalendarDays aria-hidden className="size-5" />
                        {t('communities.sessions.title')}
                    </h2>
                    {community.sessions.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title={t('communities.sessions.empty')}
                            description={t('communities.sessions.emptyHint')}
                        />
                    ) : (
                        <ul className="space-y-4">
                            {community.sessions.map((s) => (
                                <SessionCard
                                    key={s.id}
                                    session={s}
                                    isOwner={owner}
                                    onRoster={() => onRoster(s)}
                                    onCancel={() =>
                                        void run('sessions', () =>
                                            deleteJson(
                                                routes.cancelSession(s.id),
                                            ).then(() => undefined),
                                        )
                                    }
                                    onRsvp={() =>
                                        void run('sessions', async () => {
                                            const r = await postJson<{
                                                data: {
                                                    status: string;
                                                    checkout_url: string | null;
                                                };
                                            }>(routes.rsvp(s.id));
                                            if (r.data.checkout_url !== null) {
                                                window.location.assign(
                                                    r.data.checkout_url,
                                                );

                                                return;
                                            }
                                            toast.success(
                                                t(
                                                    'communities.sessions.rsvped',
                                                ),
                                            );
                                        })
                                    }
                                    onCancelRsvp={() =>
                                        void run('sessions', async () => {
                                            await deleteJson(routes.rsvp(s.id));
                                            toast.success(
                                                t(
                                                    'communities.sessions.cancelledRsvp',
                                                ),
                                            );
                                        })
                                    }
                                />
                            ))}
                        </ul>
                    )}
                    <InputError message={errorFor('sessions')} />
                </section>

                <section aria-labelledby={`${ids}-posts`} className={card}>
                    <h2
                        id={`${ids}-posts`}
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <MessageSquare aria-hidden className="size-5" />
                        {t('communities.posts.title')}
                    </h2>
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            void run('body', async () => {
                                await postJson(routes.post, { body });
                                setBody('');
                                toast.success(t('communities.posts.posted'));
                            });
                        }}
                    >
                        <Label htmlFor={`${ids}-body`}>
                            {t('communities.posts.label')}
                        </Label>
                        <p
                            id={`${ids}-hint`}
                            className="text-body text-muted-foreground"
                        >
                            {t('communities.posts.hint')}
                        </p>
                        <Textarea
                            id={`${ids}-body`}
                            aria-describedby={`${ids}-hint`}
                            rows={3}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                        <InputError message={errorFor('body')} />
                        <Button
                            type="submit"
                            disabled={busy || body.trim().length < 2}
                        >
                            {t('communities.posts.post')}
                        </Button>
                    </form>
                    {community.posts.length === 0 ? (
                        <EmptyState
                            icon={MessageSquare}
                            title={t('communities.posts.empty')}
                            description={t('communities.posts.emptyHint')}
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {community.posts.map((p) => (
                                <li key={p.id} className="space-y-2 py-4">
                                    <p className="text-body text-muted-foreground">
                                        {p.pinned ? (
                                            <span className="mr-2 inline-flex items-center gap-1 font-medium text-foreground">
                                                <Pin
                                                    aria-hidden
                                                    className="size-4"
                                                />
                                                {t('communities.posts.pinned')}
                                            </span>
                                        ) : null}
                                        {p.author}
                                    </p>
                                    <p className="text-base whitespace-pre-line">
                                        {p.body}
                                    </p>
                                    {owner || p.mine ? (
                                        <div className="flex flex-wrap gap-2">
                                            {owner ? (
                                                <Button
                                                    variant="ghost"
                                                    onClick={() =>
                                                        void run('posts', () =>
                                                            putJson(
                                                                routes.pin(
                                                                    p.id,
                                                                ),
                                                                {
                                                                    pinned: !p.pinned,
                                                                },
                                                            ).then(
                                                                () => undefined,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    {p.pinned
                                                        ? t(
                                                              'communities.posts.unpin',
                                                              {
                                                                  name: p.author,
                                                              },
                                                          )
                                                        : t(
                                                              'communities.posts.pin',
                                                              {
                                                                  name: p.author,
                                                              },
                                                          )}
                                                </Button>
                                            ) : null}
                                            <Button
                                                variant="ghost"
                                                onClick={() =>
                                                    void run(
                                                        'posts',
                                                        async () => {
                                                            await deleteJson(
                                                                routes.removePost(
                                                                    p.id,
                                                                ),
                                                            );
                                                            toast.success(
                                                                t(
                                                                    'communities.posts.removed',
                                                                ),
                                                            );
                                                        },
                                                    )
                                                }
                                            >
                                                {t('communities.posts.remove', {
                                                    name: p.author,
                                                })}
                                            </Button>
                                        </div>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                    <InputError message={errorFor('posts')} />
                </section>
            </div>

            <div className="space-y-6">
                <section aria-labelledby={`${ids}-about`} className={card}>
                    <h2
                        id={`${ids}-about`}
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <Users aria-hidden className="size-5" />
                        {t('communities.about')}
                    </h2>
                    {community.about ? (
                        <p className="text-body">{community.about}</p>
                    ) : null}
                    <p className="text-body text-muted-foreground">
                        {t('communities.ledBy', { name: community.owner })} ·{' '}
                        {t('communities.members', { count: community.members })}
                    </p>
                </section>

                <section aria-labelledby={`${ids}-resources`} className={card}>
                    <h2
                        id={`${ids}-resources`}
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <LinkIcon aria-hidden className="size-5" />
                        {t('communities.resources.title')}
                    </h2>
                    {community.resources.length === 0 ? (
                        <p className="text-body text-muted-foreground">
                            {t('communities.resources.emptyHint')}
                        </p>
                    ) : (
                        <ul className="space-y-2">
                            {community.resources.map((r) => (
                                <li
                                    key={r.id}
                                    className="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <a
                                        href={r.url}
                                        rel="noreferrer"
                                        target="_blank"
                                        className="text-base text-primary underline underline-offset-4"
                                    >
                                        {r.title}
                                    </a>
                                    {owner ? (
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                void run('resources', () =>
                                                    deleteJson(
                                                        routes.removeResource(
                                                            r.id,
                                                        ),
                                                    ).then(() => undefined),
                                                )
                                            }
                                        >
                                            {t('communities.resources.remove', {
                                                title: r.title,
                                            })}
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                    {owner ? (
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                void run('resources', async () => {
                                    await postJson(routes.addResource, {
                                        title,
                                        url,
                                    });
                                    setTitle('');
                                    setUrl('');
                                    toast.success(
                                        t('communities.resources.added'),
                                    );
                                });
                            }}
                        >
                            <div className="space-y-2">
                                <Label htmlFor={`${ids}-rt`}>
                                    {t('communities.resources.titleLabel')}
                                </Label>
                                <Input
                                    id={`${ids}-rt`}
                                    value={title}
                                    onChange={(e) => setTitle(e.target.value)}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor={`${ids}-ru`}>
                                    {t('communities.resources.url')}
                                </Label>
                                <Input
                                    id={`${ids}-ru`}
                                    type="url"
                                    value={url}
                                    onChange={(e) => setUrl(e.target.value)}
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={
                                    busy ||
                                    title.trim().length < 2 ||
                                    url.length < 10
                                }
                            >
                                {t('communities.resources.add')}
                            </Button>
                        </form>
                    ) : null}
                    <InputError message={errorFor('resources')} />
                </section>
            </div>
        </div>
    );
}

/** My communities (B17.2): each with its owner, members and next session. */
export function CommunityList({
    communities,
    href,
}: {
    communities: CommunityCard[];
    href: (id: string) => string;
}) {
    const { t } = useTranslation();
    const when = useWhen();

    if (communities.length === 0) {
        return (
            <EmptyState
                icon={Users}
                title={t('communities.mineEmpty')}
                description={t('communities.mineEmptyHint')}
            />
        );
    }

    return (
        <ul className="grid gap-4 lg:grid-cols-2">
            {communities.map((c) => (
                <li key={c.id} className={card}>
                    <div>
                        <p className="text-lg font-semibold">{c.name}</p>
                        <p className="text-body text-muted-foreground">
                            {t('communities.ledBy', { name: c.owner })} ·{' '}
                            {t('communities.members', { count: c.members })}
                        </p>
                    </div>
                    {c.next ? (
                        <p className="text-body">
                            {t('communities.nextSession', {
                                title: c.next.title,
                                date: when(c.next.starts_at),
                            })}
                        </p>
                    ) : null}
                    <Button asChild variant="outline">
                        <a href={href(c.id)}>
                            {t('communities.open', { name: c.name })}
                        </a>
                    </Button>
                </li>
            ))}
        </ul>
    );
}

/** The invite (B17.2): who leads it, and Join for verified agents and providers. */
export function JoinCard({
    invite,
    joinUrl,
    via,
    openUrl,
}: {
    invite: CommunityInvite;
    joinUrl: string;
    via: 'link' | 'qr';
    openUrl: string;
}) {
    const { t } = useTranslation();
    const [joined, setJoined] = useState(invite.member);
    const [error, setError] = useState<string | null>(null);

    return (
        <div className={`${card} max-w-2xl`}>
            {invite.about ? <p className="text-body">{invite.about}</p> : null}
            <p className="text-body text-muted-foreground">
                {t('communities.ledBy', { name: invite.owner })} ·{' '}
                {t('communities.members', { count: invite.members })}
            </p>
            {joined ? (
                <>
                    <p className="font-medium">
                        {t('communities.join.already')}
                    </p>
                    <Button asChild>
                        <a href={openUrl}>{t('communities.join.open')}</a>
                    </Button>
                </>
            ) : invite.verified ? (
                <Button
                    onClick={() =>
                        void postJson(joinUrl, { via })
                            .then(() => {
                                setJoined(true);
                                toast.success(
                                    t('communities.join.joined', {
                                        name: invite.name,
                                    }),
                                );
                            })
                            .catch((e: unknown) => setError(errorText(e)))
                    }
                >
                    {t('communities.join.join')}
                </Button>
            ) : (
                <p className="text-body">{t('communities.join.notVerified')}</p>
            )}
            <InputError message={error ?? undefined} />
        </div>
    );
}
