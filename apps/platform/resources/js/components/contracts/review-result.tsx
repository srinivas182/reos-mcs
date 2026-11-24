import {
    AlertTriangle,
    CalendarPlus,
    CheckCircle2,
    ExternalLink,
    Search,
    Send,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ContractReview } from './types';

/**
 * Contract review result (agent screen 65, web screen 15): findings ranked
 * High and Check with pages, dates and rules; deadlines ready for the
 * calendar; key terms with what changed since the earlier version. Only
 * facts from the document; not legal advice.
 */
export function ReviewResult({
    review,
    onPage,
    onAddDeadlines,
    onSendToBroker,
    busy = false,
}: {
    review: ContractReview;
    onPage?: (page: number) => void;
    onAddDeadlines: () => void;
    onSendToBroker: () => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const day = (value: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        }).format(new Date(`${value}T12:00:00`));
    const page = (n: number | null) =>
        n === null ? null : onPage ? (
            <button
                type="button"
                onClick={() => onPage(n)}
                className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
            >
                {t('contracts.web.pageShort', { page: n })}
            </button>
        ) : (
            <span className="text-sm text-muted-foreground">
                {t('contracts.web.pageShort', { page: n })}
            </span>
        );

    return (
        <div className="space-y-6">
            <div className="flex flex-wrap gap-3">
                <Button
                    type="button"
                    onClick={onAddDeadlines}
                    disabled={
                        busy ||
                        review.deadlines_added ||
                        review.deadlines.length === 0
                    }
                >
                    {review.deadlines_added ? (
                        <CheckCircle2 aria-hidden />
                    ) : (
                        <CalendarPlus aria-hidden />
                    )}
                    {review.deadlines_added
                        ? t('contracts.web.deadlinesAdded')
                        : t('contracts.web.addDeadlines')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    onClick={onSendToBroker}
                    disabled={busy || review.broker_question !== null}
                >
                    <Send aria-hidden />
                    {review.broker_question !== null
                        ? t('contracts.web.sentToBroker')
                        : t('contracts.web.sendToBroker')}
                </Button>
            </div>

            <section aria-labelledby="cr-findings" className="space-y-3">
                <h2 id="cr-findings" className="text-lg font-semibold">
                    {t('contracts.web.findings')}
                </h2>
                {review.findings.length === 0 ? (
                    <EmptyState
                        icon={Search}
                        title={t('contracts.web.noFindings')}
                        description={t('contracts.web.noFindingsBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {review.findings.map((f, i) => (
                            <li
                                key={`${f.code}-${i}`}
                                className={
                                    f.severity === 'high'
                                        ? 'space-y-1 rounded-reos-md border border-reos-tone-danger-fg/30 bg-reos-tone-danger-bg p-4 text-reos-tone-danger-fg'
                                        : 'space-y-1 rounded-reos-md border border-reos-tone-warning-fg/30 bg-reos-tone-warning-bg p-4 text-reos-tone-warning-fg'
                                }
                            >
                                <p className="flex flex-wrap items-center gap-2 text-base font-semibold">
                                    <AlertTriangle
                                        aria-hidden
                                        className="size-5"
                                    />
                                    <Badge
                                        variant="outline"
                                        className="border-current text-current"
                                    >
                                        {t(
                                            f.severity === 'high'
                                                ? 'contracts.severity.high'
                                                : 'contracts.severity.check',
                                        )}
                                    </Badge>
                                    {f.title}
                                </p>
                                <p className="text-base">{f.detail}</p>
                                <p className="flex flex-wrap items-center gap-3 text-base">
                                    {f.date ? (
                                        <span className="font-medium">
                                            {day(f.date)}
                                        </span>
                                    ) : null}
                                    {page(f.page)}
                                    {f.citation ? (
                                        f.url ? (
                                            <a
                                                href={f.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="inline-flex min-h-12 items-center gap-1 font-medium underline underline-offset-4"
                                            >
                                                {f.citation}
                                                <ExternalLink
                                                    aria-hidden
                                                    className="size-4"
                                                />
                                            </a>
                                        ) : (
                                            <span>{f.citation}</span>
                                        )
                                    ) : null}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {review.deadlines.length > 0 ? (
                <section aria-labelledby="cr-deadlines" className="space-y-3">
                    <h2 id="cr-deadlines" className="text-lg font-semibold">
                        {t('contracts.web.deadlines')}
                    </h2>
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {review.deadlines.map((d) => (
                            <li
                                key={d.code}
                                className="flex flex-wrap items-center justify-between gap-3 p-4"
                            >
                                <div>
                                    <p className="text-base font-semibold">
                                        {d.label}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {d.basis}
                                    </p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <span className="text-base font-medium">
                                        {day(d.due_on)}
                                    </span>
                                    {page(d.page)}
                                </div>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            <section aria-labelledby="cr-terms" className="space-y-3">
                <h2 id="cr-terms" className="text-lg font-semibold">
                    {t('contracts.web.keyTerms')}
                </h2>
                <dl className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                    {review.terms.map((term) => (
                        <div
                            key={term.code}
                            className="grid gap-1 p-4 sm:grid-cols-[1fr_2fr]"
                        >
                            <dt className="text-base text-muted-foreground">
                                {term.label}
                            </dt>
                            <dd className="flex flex-wrap items-center gap-2 text-base">
                                <span className="font-medium">
                                    {term.value}
                                </span>
                                {term.changed ? (
                                    <>
                                        <Badge>
                                            {t('contracts.web.changed')}
                                        </Badge>
                                        <span className="text-muted-foreground">
                                            {t('contracts.web.was', {
                                                value: term.previous ?? '—',
                                            })}
                                        </span>
                                    </>
                                ) : null}
                                {page(term.page)}
                            </dd>
                        </div>
                    ))}
                </dl>
                {review.addenda.length > 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('contracts.web.addenda')}:{' '}
                        {review.addenda
                            .map((a) =>
                                t(`contracts.web.addendaNames.${a as 'hoa'}`),
                            )
                            .join(', ')}
                    </p>
                ) : null}
            </section>
            <p className="text-sm text-muted-foreground">
                {t('contracts.web.notLegalAdvice')}
            </p>
        </div>
    );
}
