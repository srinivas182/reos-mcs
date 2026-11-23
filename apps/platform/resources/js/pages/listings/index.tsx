import { Head, Link, router } from '@inertiajs/react';
import { BadgeCheck, Home, IdCard, SearchX } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { ListingCard } from '@/components/listings/listing-card';
import { MemberCard } from '@/components/listings/member-card';
import { PageCard } from '@/components/listings/page-card';
import { PostDialog } from '@/components/listings/post-dialog';
import type {
    ListingRow,
    ListingsOverview,
    MemberRow,
    PageSettings,
    PostDraft,
} from '@/components/listings/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';
import { getJson, postJson, putJson } from '@/lib/content';
import { undoToast } from '@/lib/undo-toast';
import { index as deals } from '@/routes/deals';
import { cma } from '@/routes/deals/listings';
import { confirm, decline, unlink } from '@/routes/deals/listings/members';
import { update as pageUpdate } from '@/routes/deals/listings/page';
import {
    show as postShow,
    update as postUpdate,
} from '@/routes/deals/listings/post';
import { edit as profile } from '@/routes/profile';

/**
 * My listings (B10.1, beside Deals in the agent journey): MLS listings where
 * the agent is the listing or co-listing agent, on the market first. The
 * agent's MLS member record is found by their license number and confirmed
 * here. Simulator data says "Sample data".
 */
export default function ListingsPage({
    listings,
    page,
}: {
    listings: ListingsOverview;
    page: PageSettings;
}) {
    const { t, i18n } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [post, setPost] = useState<{
        open: boolean;
        draft: PostDraft | null;
    }>({
        open: false,
        draft: null,
    });
    const openPost = async (row: ListingRow) => {
        setPost({ open: true, draft: null });
        try {
            const { data } = await getJson<{ data: PostDraft }>(
                postShow(row.uuid).url,
            );
            setPost({ open: true, draft: data });
        } catch (e) {
            setPost({ open: false, draft: null });
            setError(e instanceof Error ? e.message : String(e));
        }
    };
    const savePage = async (published: boolean, bio: string) => {
        await putJson(pageUpdate().url, { published, bio });
        router.reload({ only: ['page'] });
        toast.success(t('listings.page.saved'));
    };
    const savePost = async (caption: string, photos: string[]) => {
        if (!post.draft) return;
        const { data } = await putJson<{ data: PostDraft }>(
            postUpdate(post.draft.listing).url,
            { caption, photos },
        );
        setPost({ open: true, draft: data });
        toast.success(t('listings.post.saved'));
    };
    const body = (member: MemberRow) => ({
        mls: member.mls,
        member_key: member.member_key,
    });
    const send = async (url: string, member: MemberRow) => {
        setBusy(true);

        try {
            await postJson(url, body(member));
            setError(null);
            router.reload({ only: ['listings'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const unlinkMember = async (member: MemberRow) => {
        await send(unlink().url, member);
        undoToast({
            message: t('listings.linked.unlinked', { mls: member.short_name }),
            onUndo: () => send(confirm().url, member),
        });
    };
    const updated = listings.synced_at
        ? new Intl.DateTimeFormat(i18n.language, {
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(listings.synced_at))
        : null;
    const nothingFound =
        listings.licensed &&
        listings.linked.length === 0 &&
        listings.candidates.length === 0;

    return (
        <>
            <Head title={t('listings.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('listings.title')}
                    description={t('listings.description')}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={deals().url}>
                                {t('listings.deals')}
                            </Link>
                        </Button>
                    }
                />
                {listings.sample || updated ? (
                    <p className="flex flex-wrap items-center gap-3 text-base text-muted-foreground">
                        {listings.sample ? (
                            <span className="rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 font-semibold text-reos-tone-info-fg">
                                {t('listings.sample')}
                            </span>
                        ) : null}
                        {listings.sample ? (
                            <span>{t('listings.sampleNote')}</span>
                        ) : null}
                        {updated ? (
                            <span>
                                {t('listings.updated', { time: updated })}
                            </span>
                        ) : null}
                    </p>
                ) : null}
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                {listings.candidates.map((member) => (
                    <MemberCard
                        key={`${member.mls}-${member.member_key}`}
                        member={member}
                        busy={busy}
                        onConfirm={() => void send(confirm().url, member)}
                        onDecline={() => void send(decline().url, member)}
                    />
                ))}
                {!listings.licensed ? (
                    <EmptyState
                        icon={IdCard}
                        title={t('listings.noLicence.title')}
                        description={t('listings.noLicence.description')}
                        action={
                            <Button asChild>
                                <Link href={profile().url}>
                                    {t('listings.noLicence.action')}
                                </Link>
                            </Button>
                        }
                    />
                ) : null}
                {nothingFound ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('listings.notFound.title')}
                        description={t('listings.notFound.description')}
                    />
                ) : null}
                {listings.linked.length > 0 ? (
                    <section aria-labelledby="linked" className="space-y-3">
                        <h2 id="linked" className="text-lg font-semibold">
                            {t('listings.linked.title')}
                        </h2>
                        <ul className="flex flex-wrap gap-3">
                            {listings.linked.map((member) => (
                                <li
                                    key={`${member.mls}-${member.member_key}`}
                                    className="flex flex-wrap items-center gap-3 rounded-reos-lg border border-border bg-card px-4 py-2"
                                >
                                    <BadgeCheck
                                        aria-hidden
                                        className="size-5"
                                    />
                                    <span className="text-base">
                                        <span className="font-semibold">
                                            {member.short_name}
                                        </span>{' '}
                                        · {member.name}
                                        {member.office
                                            ? ` · ${member.office}`
                                            : ''}
                                    </span>
                                    <Button
                                        variant="ghost"
                                        disabled={busy}
                                        aria-label={t(
                                            'listings.linked.unlinkLabel',
                                            { mls: member.short_name },
                                        )}
                                        onClick={() =>
                                            void unlinkMember(member)
                                        }
                                    >
                                        {t('listings.linked.unlink')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    </section>
                ) : null}
                {listings.linked.length > 0 &&
                listings.listings.length === 0 ? (
                    <EmptyState
                        icon={Home}
                        title={t('listings.empty.title')}
                        description={t('listings.empty.description')}
                    />
                ) : null}
                {listings.listings.length > 0 ? (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {listings.listings.map((row) => (
                            <li key={row.uuid}>
                                <ListingCard
                                    listing={row}
                                    onPost={() => void openPost(row)}
                                    cmaHref={cma(row.uuid).url}
                                />
                            </li>
                        ))}
                    </ul>
                ) : null}
                {listings.linked.length > 0 ? (
                    <PageCard page={page} onSave={savePage} />
                ) : null}
            </div>
            <PostDialog
                draft={post.draft}
                open={post.open}
                onOpenChange={(open) => setPost((p) => ({ ...p, open }))}
                onSave={savePost}
            />
        </>
    );
}
