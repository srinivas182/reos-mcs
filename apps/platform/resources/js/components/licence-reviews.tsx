import { Link, router } from '@inertiajs/react';
import { ChevronRight, ClipboardCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { show } from '@/routes/people';
import { approve, reject } from '@/routes/people/licences';
import type { Licence } from '@/types';

export type LicenceReviewItem = {
    id: number;
    person: { id: number; name: string | null };
    state: string;
    number: string;
    note: string | null;
    created_at: string | null;
};

/**
 * "Licences to review" on People (A5.1): licences with no regulator match
 * yet, oldest first. No licence counts until a reviewer approves it.
 */
export function LicenceReviewQueue({ items }: { items: LicenceReviewItem[] }) {
    const { t } = useTranslation();

    if (items.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="licence-reviews"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <h2
                id="licence-reviews"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <ClipboardCheck aria-hidden className="size-5 text-primary" />
                {t('licences.reviews.title', { count: items.length })}
            </h2>
            <ul className="divide-y divide-border">
                {items.map((item) => {
                    const name = item.person.name ?? t('people.unnamed');

                    return (
                        <li key={item.id}>
                            <Link
                                href={show(item.person.id)}
                                className="flex min-h-12 items-center gap-4 py-3 hover:bg-muted focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold">
                                        {name} · {item.state} #{item.number}
                                    </p>
                                    {item.note ? (
                                        <p className="text-body text-muted-foreground">
                                            {item.note}
                                        </p>
                                    ) : null}
                                </div>
                                <ChevronRight
                                    aria-hidden
                                    className="size-5 text-muted-foreground"
                                />
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/** A person's licences on their People page, with Approve and Reject for those waiting. */
export function PersonLicences({ licences }: { licences: Licence[] }) {
    const { t } = useTranslation();
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [note, setNote] = useState('');

    if (licences.length === 0) {
        return (
            <p className="text-body text-muted-foreground">
                {t('licences.reviews.none')}
            </p>
        );
    }

    return (
        <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
            {licences.map((licence) => (
                <li key={licence.id} className="space-y-3 p-4">
                    <p className="font-semibold">
                        {t('licences.title', {
                            state: licence.state.name,
                            type: t(`licences.types.${licence.type}`),
                        })}{' '}
                        · #{licence.number}
                    </p>
                    <p className="text-body text-muted-foreground">
                        {t(`licences.statuses.${licence.status}`)}
                        {licence.holder_name
                            ? ` · ${t('licences.reviews.onRecord', { name: licence.holder_name })}`
                            : ''}
                        {licence.review_note ? ` · ${licence.review_note}` : ''}
                    </p>
                    {licence.status === 'pending_review' ? (
                        rejecting === licence.id ? (
                            <form
                                className="space-y-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    router.post(
                                        reject(licence.id).url,
                                        { note },
                                        {
                                            preserveScroll: true,
                                            onSuccess: () => {
                                                setRejecting(null);
                                                setNote('');
                                            },
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor={`reject-${licence.id}`}>
                                        {t('licences.reviews.why')}
                                    </Label>
                                    <Textarea
                                        id={`reject-${licence.id}`}
                                        value={note}
                                        onChange={(e) =>
                                            setNote(e.target.value)
                                        }
                                        required
                                        maxLength={500}
                                    />
                                </div>
                                <div className="flex gap-3">
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={note.trim() === ''}
                                    >
                                        {t('licences.reviews.reject')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setRejecting(null)}
                                    >
                                        {t('common.cancel')}
                                    </Button>
                                </div>
                            </form>
                        ) : (
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    onClick={() =>
                                        router.post(
                                            approve(licence.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('licences.reviews.approve')}
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => setRejecting(licence.id)}
                                >
                                    {t('licences.reviews.reject')}
                                </Button>
                            </div>
                        )
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
