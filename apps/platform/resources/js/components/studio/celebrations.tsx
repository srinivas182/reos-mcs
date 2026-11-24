import { PartyPopper } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import type { Generation } from './types';

/** A celebration moment (B12.3), as the API and page send it. */
export type Milestone =
    | 'just_listed'
    | 'under_contract'
    | 'just_sold'
    | 'first_closing'
    | 'ce_complete'
    | 'license_renewed'
    | 'anniversary';

export type CelebrationRow = {
    id: string;
    milestone: Milestone;
    label: string | null;
    post: string | null;
    generation: Generation | null;
    shown_at: string | null;
    created_at: string | null;
};

export type CelebrationList = {
    today: CelebrationRow | null;
    waiting: CelebrationRow[];
};

/**
 * Celebration moments on the web (B12.3; screen 163): today's milestone,
 * ready to post and free, and the ones waiting. Each is also a draft in My
 * posts, where it can be edited, checked and shared.
 */
export function Celebrations({ data }: { data: CelebrationList }) {
    const { t } = useTranslation();
    const today = data.today;
    const design = today?.generation?.designs[0];

    return (
        <section
            aria-labelledby="celebrations-title"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="celebrations-title" className="text-lg font-semibold">
                    {t('studio.celebrate.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('studio.celebrate.free')}
                </p>
            </div>
            {today === null && data.waiting.length === 0 ? (
                <EmptyState
                    icon={PartyPopper}
                    title={t('studio.celebrate.none')}
                    description={t('studio.celebrate.noneHint')}
                />
            ) : null}
            {today ? (
                <div className="flex flex-wrap gap-4">
                    {design ? (
                        <img
                            src={design.url}
                            alt={t(`studio.celebrate.names.${today.milestone}`)}
                            className="size-48 rounded-reos-md object-cover"
                        />
                    ) : null}
                    <div className="min-w-64 flex-1 space-y-2">
                        <Badge>{t('studio.celebrate.today')}</Badge>
                        <p className="text-base font-semibold">
                            {t(`studio.celebrate.names.${today.milestone}`)}
                        </p>
                        <p className="text-base">
                            {today.generation?.captions[0]?.text}
                        </p>
                    </div>
                </div>
            ) : null}
            {data.waiting.length > 0 ? (
                <div className="space-y-2">
                    <h3 className="text-base font-semibold">
                        {t('studio.celebrate.waiting')}
                    </h3>
                    <ul className="space-y-2">
                        {data.waiting.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-reos-md border border-border p-3"
                            >
                                <span className="text-base">
                                    {t(`studio.celebrate.names.${c.milestone}`)}
                                </span>
                                {c.label ? (
                                    <span className="text-sm text-muted-foreground">
                                        {c.label}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
