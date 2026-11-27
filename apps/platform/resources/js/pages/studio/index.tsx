import { Head, Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { PageHeader } from '@/components/page-header';
import { AccountsList } from '@/components/social/accounts-list';
import { Composer } from '@/components/social/composer';
import { PostsList } from '@/components/social/posts-list';
import { Maker } from '@/components/studio/maker';
import type {
    Generation,
    Maker as MakerData,
    Video,
    MakeRequest,
} from '@/components/studio/types';
import { Versions } from '@/components/studio/versions';
import { Videos } from '@/components/studio/videos';
import type {
    CheckResult,
    Draft,
    ListingDraft,
    PostList,
    SocialAccount,
    SocialPost,
} from '@/components/social/types';
import { deleteJson, postForm, postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { index as connections } from '@/routes/connections';
import {
    calendar as calendarRoute,
    media as mediaRoute,
} from '@/routes/studio';
import {
    show as videoRoute,
    store as makeVideoRoute,
} from '@/routes/studio/videos';
import { refresh as refreshRoute } from '@/routes/studio/accounts';
import {
    make as makeRoute,
    retone as retoneRoute,
} from '@/routes/studio/generations';
import {
    check as checkRoute,
    destroy,
    fix as fixRoute,
    repost as repostRoute,
    restore,
    store,
    update,
} from '@/routes/studio/posts';

const blank = (listing: ListingDraft | null): Draft => ({
    body: listing?.caption ?? '',
    link: '',
    media: listing
        ? listing.chosen.map((url) => ({
              value: url,
              preview: url,
              kind: 'image',
          }))
        : [],
    picks: [],
    when: 'now',
    at: '',
});

const toDraft = (post: SocialPost): Draft => ({
    body: post.body,
    link: post.link ?? '',
    media: post.media.map((m) => ({
        value: m.url,
        preview: m.url,
        kind: m.kind,
    })),
    picks: post.targets.map((t) => ({
        connection: 0,
        channel: t.channel,
        id: t.target_id,
    })),
    when: post.status === 'scheduled' ? 'later' : 'draft',
    at: post.scheduled_at ? post.scheduled_at.slice(0, 16) : '',
});

/**
 * Content studio (B11; agent web screen 17 with screens 108, 169 and 170):
 * social accounts, a post to publish or schedule (or one from a listing
 * post) with the broker name and checks, and my posts with their results.
 */
export default function StudioPage({
    accounts: initialAccounts,
    posts: initialPosts,
    listing,
    maker,
    plan = null,
}: {
    accounts: SocialAccount[];
    posts: PostList;
    listing: ListingDraft | null;
    maker: MakerData;
    /** Plan a post from the calendar (B12.3): the composer opens scheduled for that day and time. */
    plan?: string | null;
}) {
    const { t, i18n } = useTranslation();
    const [accounts, setAccounts] = useState(initialAccounts);
    const [posts, setPosts] = useState(initialPosts);
    const [check, setCheck] = useState<CheckResult | null>(null);
    const [editing, setEditing] = useState<{
        id: string | null;
        draft: Draft;
        key: number;
    }>({
        id: null,
        draft: plan
            ? { ...blank(listing), when: 'later', at: plan }
            : blank(listing),
        key: 0,
    });
    const [error, setError] = useState<string | null>(null);
    const [generation, setGeneration] = useState<Generation | null>(null);
    const [making, setMaking] = useState(false);
    const [videos, setVideos] = useState<Video[]>(maker.videos);
    const rendering = videos.some((v) => v.status === 'rendering');

    // While a video renders, look again every few seconds (B12.2).
    useEffect(() => {
        if (!rendering) return;
        const timer = setInterval(() => {
            videos
                .filter((v) => v.status === 'rendering')
                .forEach((v) => {
                    void fetch(videoRoute(v.id).url, {
                        headers: { Accept: 'application/json' },
                    })
                        .then((r) =>
                            r.ok
                                ? (r.json() as Promise<{ data: Video }>)
                                : null,
                        )
                        .then((r) => {
                            if (r && r.data.status !== 'rendering') {
                                setVideos((list) =>
                                    list.map((x) =>
                                        x.id === r.data.id ? r.data : x,
                                    ),
                                );
                            }
                        })
                        .catch(() => undefined);
                });
        }, 3000);
        return () => clearInterval(timer);
    }, [rendering, videos]);
    // A design made from a listing posts with the listing's MLS line (B12.1).
    const [studioListing, setStudioListing] = useState<string | null>(null);

    const body = useCallback(
        (draft: Draft) => ({
            body: draft.body,
            media: draft.media.map((m) => m.value),
            link: draft.link.trim() === '' ? null : draft.link.trim(),
            targets: draft.picks,
            when: draft.when,
            at:
                draft.when === 'later' && draft.at
                    ? new Date(draft.at).toISOString()
                    : null,
            listing: studioListing ?? listing?.listing ?? null,
            ask_broker: draft.ask_broker?.trim()
                ? draft.ask_broker.trim()
                : null,
        }),
        [listing, studioListing],
    );
    const onCheck = useCallback(
        (draft: Draft) => {
            void postJson<{ data: CheckResult }>(checkRoute().url, {
                ...body(draft),
                when: 'draft',
            })
                .then(({ data }) => setCheck(data))
                .catch(() => undefined);
        },
        [body],
    );
    const reload = (list: PostList) => setPosts(list);
    const refreshList = async () => {
        const response = await fetch(window.location.pathname, {
            headers: {
                'X-Inertia': 'true',
                'X-Inertia-Partial-Data': 'posts',
                'X-Inertia-Partial-Component': 'studio/index',
                Accept: 'application/json',
            },
        });
        if (response.ok) {
            const page = (await response.json()) as {
                props: { posts: PostList };
            };
            reload(page.props.posts);
        }
    };

    const submit = async (draft: Draft) => {
        try {
            const { data } = editing.id
                ? await putJson<{ data: SocialPost }>(
                      update(editing.id).url,
                      body(draft),
                  )
                : await postJson<{ data: SocialPost }>(
                      store().url,
                      body(draft),
                  );
            setError(null);
            toast.success(
                draft.when === 'now'
                    ? t('social.compose.published')
                    : draft.when === 'later' && data.scheduled_at
                      ? t('social.compose.scheduled', {
                            date: new Intl.DateTimeFormat(i18n.language, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            }).format(new Date(data.scheduled_at)),
                        })
                      : t('social.compose.saved'),
            );
            setEditing({ id: null, draft: blank(null), key: editing.key + 1 });
            setStudioListing(null);
            await refreshList();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('social.studio.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('social.studio.title')}
                    description={t('social.studio.description')}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={calendarRoute().url}>
                                <CalendarDays aria-hidden />
                                {t('social.calendar.title')}
                            </Link>
                        </Button>
                    }
                />
                <Maker
                    data={maker}
                    busy={making}
                    onMake={(request: MakeRequest) => {
                        setMaking(true);
                        setError(null);
                        void postJson<{ data: Generation }>(
                            makeRoute().url,
                            request,
                        )
                            .then(({ data }) => setGeneration(data))
                            .catch((e: unknown) =>
                                setError(
                                    e instanceof Error ? e.message : String(e),
                                ),
                            )
                            .finally(() => setMaking(false));
                    }}
                />
                <Videos
                    data={{ ...maker, videos }}
                    busy={making}
                    onMake={(id) => {
                        setMaking(true);
                        setError(null);
                        void postJson<{ data: Video }>(makeVideoRoute().url, {
                            source: { type: 'listing', id },
                        })
                            .then(({ data }) =>
                                setVideos((list) => [data, ...list]),
                            )
                            .catch((e: unknown) =>
                                setError(
                                    e instanceof Error ? e.message : String(e),
                                ),
                            )
                            .finally(() => setMaking(false));
                    }}
                    onUse={(v) => {
                        if (!v.video) return;
                        setStudioListing(v.listing);
                        setEditing({
                            id: null,
                            draft: {
                                ...blank(null),
                                body: v.title,
                                media: [
                                    {
                                        value: v.video.path,
                                        preview: v.video.url,
                                        kind: 'video',
                                    },
                                ],
                            },
                            key: editing.key + 1,
                        });
                    }}
                />
                {generation ? (
                    <Versions
                        generation={generation}
                        busy={making}
                        onRetone={(index, tone) => {
                            setMaking(true);
                            void postJson<{ data: Generation }>(
                                retoneRoute(generation.id).url,
                                { index, tone },
                            )
                                .then(({ data }) => setGeneration(data))
                                .finally(() => setMaking(false));
                        }}
                        onUse={(caption, media) => {
                            setStudioListing(generation.listing);
                            setEditing({
                                id: null,
                                draft: {
                                    ...blank(null),
                                    body: caption,
                                    media: media.map((m) => ({
                                        value: m.path,
                                        preview: m.url,
                                        kind: 'image',
                                    })),
                                },
                                key: editing.key + 1,
                            });
                        }}
                    />
                ) : null}
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <div className="grid gap-6 xl:grid-cols-[3fr_2fr]">
                    <Composer
                        key={editing.key}
                        accounts={accounts}
                        draft={editing.draft}
                        listing={editing.id ? null : listing}
                        check={check}
                        onCheck={onCheck}
                        onUpload={async (file) => {
                            const form = new FormData();
                            form.append('file', file);
                            const { data } = await postForm<{
                                data: {
                                    path: string;
                                    url: string;
                                    kind: string;
                                };
                            }>(mediaRoute().url, form);
                            return {
                                value: data.path,
                                preview: data.url,
                                kind: data.kind,
                            };
                        }}
                        onSubmit={(draft) => void submit(draft)}
                        onFix={async (text) =>
                            (
                                await postJson<{ data: { body: string } }>(
                                    fixRoute().url,
                                    { body: text },
                                )
                            ).data.body
                        }
                    />
                    <AccountsList
                        accounts={accounts}
                        connectUrl={connections().url}
                        onRefresh={(account) => {
                            if (!account.connection) return;
                            void postJson<{ data: SocialAccount['targets'] }>(
                                refreshRoute(account.connection.id).url,
                            ).then(({ data }) =>
                                setAccounts((all) =>
                                    all.map((a) =>
                                        a.integration === account.integration
                                            ? { ...a, targets: data }
                                            : a,
                                    ),
                                ),
                            );
                        }}
                    />
                </div>
                <PostsList
                    posts={posts}
                    onEdit={(post) => {
                        const draft = toDraft(post);
                        const connectionOf = (channel: string, id: string) =>
                            accounts.find((a) =>
                                a.targets.some(
                                    (x) => x.channel === channel && x.id === id,
                                ),
                            )?.connection?.id ?? 0;
                        setEditing({
                            id: post.id,
                            draft: {
                                ...draft,
                                picks: draft.picks.map((p) => ({
                                    ...p,
                                    connection: connectionOf(p.channel, p.id),
                                })),
                            },
                            key: editing.key + 1,
                        });
                    }}
                    onRemove={(post) => {
                        void deleteJson(destroy(post.id).url).then(async () => {
                            await refreshList();
                            undoToast({
                                message: t('social.compose.removed'),
                                onUndo: async () => {
                                    await postJson(restore(post.id).url);
                                    await refreshList();
                                },
                            });
                        });
                    }}
                    onRepost={(post) => {
                        void postJson<{ data: SocialPost }>(
                            repostRoute(post.id).url,
                        ).then(async () => {
                            toast.success(t('social.compose.reposted'));
                            await refreshList();
                        });
                    }}
                />
            </div>
        </>
    );
}
