import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { statusTone } from '@/components/builder/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { item as itemRoute } from '@/routes/builder/review';
import type { ReviewItemRow } from './types';

/** The lesson's check-ins and flashcards on screen 44 (A8.1): approve or reject each. */
export function ReviewItems({
    lessonId,
    items,
    locked,
}: {
    lessonId: number;
    items: ReviewItemRow[];
    locked: boolean;
}) {
    const { t } = useTranslation();
    const decide = (item: ReviewItemRow, decision: 'approve' | 'reject') =>
        router.post(
            itemRoute([lessonId, item.id]).url,
            { decision },
            { preserveScroll: true },
        );

    return (
        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-4">
            <h2 className="text-lg font-semibold">{t('review.page.items')}</h2>
            <ul className="divide-y divide-border">
                {items.map((item) => {
                    const label =
                        item.kind === 'checkin'
                            ? t('review.page.checkin', {
                                  n: (item.after_line ?? 0) + 1,
                              })
                            : t('review.page.flashcard');
                    const text =
                        item.kind === 'checkin'
                            ? item.content.en?.prompt
                            : `${item.content.en?.front ?? ''} — ${item.content.en?.back ?? ''}`;

                    return (
                        <li key={item.id} className="space-y-2 py-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-sm font-semibold text-muted-foreground">
                                    {label}
                                </span>
                                <span
                                    className={cn(
                                        'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                        statusTone(
                                            item.status === 'rejected'
                                                ? 'failed'
                                                : item.status === 'approved'
                                                  ? 'approved'
                                                  : 'in_review',
                                        ),
                                    )}
                                >
                                    {t(`review.page.itemStatus.${item.status}`)}
                                </span>
                            </div>
                            <p className="text-base">{text}</p>
                            {!locked && item.status === 'to_review' ? (
                                <div className="flex gap-2">
                                    <Button
                                        size="sm"
                                        aria-label={`${t('review.page.approve')}: ${label} ${item.id}`}
                                        onClick={() => decide(item, 'approve')}
                                    >
                                        {t('review.page.approve')}
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        aria-label={`${t('review.page.reject')}: ${label} ${item.id}`}
                                        onClick={() => decide(item, 'reject')}
                                    >
                                        {t('review.page.reject')}
                                    </Button>
                                </div>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
