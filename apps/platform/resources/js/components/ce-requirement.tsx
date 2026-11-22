import { CheckCircle2, Circle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { CeRequirement } from '@/types';

const day = (value: string | null, language: string) =>
    value
        ? new Intl.DateTimeFormat(language, {
              month: 'short',
              day: 'numeric',
              year: 'numeric',
              timeZone: 'UTC',
          }).format(new Date(`${value}T00:00:00Z`))
        : '';

/** The dashboard's CE tile (web journey screen 12): "CE hours 9/18 · renews Mar 31, 2027". */
export function CeHoursTile({ ce }: { ce: CeRequirement }) {
    const { t, i18n } = useTranslation();
    const missing = ce.parts.filter(
        (p) => !p.done && p.kind !== 'elective',
    ).length;

    return (
        <section
            aria-label={t('ce.tile.label')}
            className="space-y-1 rounded-reos-lg border border-border bg-card p-4"
        >
            <p className="text-sm text-muted-foreground">
                {t('ce.tile.title')}
            </p>
            <p className="text-2xl font-semibold text-foreground">
                {t('ce.tile.hours', {
                    done: ce.hours_done,
                    required: ce.hours_required,
                })}
            </p>
            <p className="text-sm text-muted-foreground">
                {[
                    ce.due_on
                        ? t('ce.tile.renews', {
                              date: day(ce.due_on, i18n.language),
                          })
                        : null,
                    ce.complete
                        ? t('ce.done')
                        : missing > 0
                          ? t('ce.tile.coursesLeft', { count: missing })
                          : null,
                ]
                    .filter(Boolean)
                    .join(' · ')}
            </p>
        </section>
    );
}

/**
 * "What do I still need?" (A5.2): hours done of the period's requirement,
 * each required course and the electives, the deferral option and where
 * each rule comes from.
 */
export function CeRequirementList({ ce }: { ce: CeRequirement }) {
    const { t, i18n } = useTranslation();
    const percent =
        ce.hours_required > 0
            ? Math.min(
                  100,
                  Math.round((ce.hours_done / ce.hours_required) * 100),
              )
            : 0;

    return (
        <div className="space-y-3">
            <div className="space-y-1">
                <p className="font-semibold text-foreground">
                    {ce.complete
                        ? t('ce.done')
                        : t('ce.left', {
                              hours: ce.hours_left,
                              date: day(ce.due_on, i18n.language),
                          })}
                </p>
                <div
                    role="progressbar"
                    aria-label={t('ce.progress')}
                    aria-valuemin={0}
                    aria-valuemax={ce.hours_required}
                    aria-valuenow={ce.hours_done}
                    className="h-2 overflow-hidden rounded-reos-pill bg-muted"
                >
                    <div
                        className="h-full bg-primary"
                        style={{ width: `${percent}%` }}
                    />
                </div>
                {ce.first_renewal ? (
                    <p className="text-body text-muted-foreground">
                        {t('ce.firstRenewal')}
                    </p>
                ) : null}
            </div>
            <ul className="space-y-1">
                {ce.parts.map((part) => (
                    <li
                        key={part.code}
                        className="flex items-start gap-2 text-body"
                    >
                        {part.done ? (
                            <CheckCircle2
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                            />
                        ) : (
                            <Circle
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0 text-muted-foreground"
                            />
                        )}
                        <span
                            className={cn(
                                'flex-1',
                                part.done && 'text-muted-foreground',
                            )}
                        >
                            {part.title}
                            <span className="sr-only">
                                {part.done ? ` (${t('ce.partDone')})` : ''}
                            </span>
                        </span>
                        <span className="font-medium whitespace-nowrap">
                            {t('ce.partHours', {
                                done: part.hours_done,
                                hours: part.hours,
                            })}
                        </span>
                    </li>
                ))}
            </ul>
            {ce.deferral && !ce.complete ? (
                <p className="text-body text-muted-foreground">
                    {t('ce.deferral', {
                        fee: ce.deferral.fee_usd,
                        days: ce.deferral.days,
                    })}
                </p>
            ) : null}
            <p className="text-sm text-muted-foreground">
                {t('ce.sources', {
                    sources: [
                        ...new Set(ce.sources.map((s) => s.citation)),
                    ].join('; '),
                })}
            </p>
        </div>
    );
}
