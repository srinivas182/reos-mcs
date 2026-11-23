import {
    CalendarClock,
    CheckCircle2,
    CircleAlert,
    Clock,
    ExternalLink,
    Lock,
    Pencil,
    Trash2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { nativeSelect } from '@/lib/tenants';
import { money, useDateText } from './deal-card';
import { TOOL_NAMES, type DealFull, type DealStage } from './types';

const STAGES: DealStage[] = ['active', 'under_contract', 'closed', 'cancelled'];

/**
 * One deal (screen 14, opened from the board): price and commission,
 * parties, key dates, documents. A deal from a tool says so and opens there;
 * a REOS deal can be moved, edited or deleted.
 */
export function DealPanel({
    deal,
    onMove,
    onEdit,
    onDelete,
}: {
    deal: DealFull;
    onMove: (stage: DealStage) => void;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const { t, i18n } = useTranslation();
    const dateText = useDateText();
    const tool = TOOL_NAMES[deal.source] ?? deal.source;

    return (
        <div className="space-y-6">
            <div className="space-y-1">
                <p className="text-body text-muted-foreground">
                    {[
                        deal.city,
                        t(`deals.sides.${deal.side}`),
                        t(`deals.stages.${deal.stage}`),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
                <p className="text-body">
                    {[
                        deal.price !== null
                            ? t('deals.price', {
                                  price: money(deal.price, i18n.language),
                              })
                            : null,
                        deal.commission !== null
                            ? t('deals.commission', {
                                  amount: money(deal.commission, i18n.language),
                              })
                            : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </p>
            </div>

            {deal.read_only ? (
                <div className="space-y-3 rounded-reos-lg border border-border bg-muted/40 p-4">
                    <p className="flex items-center gap-2 text-body">
                        <Lock aria-hidden className="size-4" />
                        {t('deals.readOnly', { tool })}
                    </p>
                    {deal.external_url ? (
                        <Button variant="outline" asChild>
                            <a
                                href={deal.external_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink aria-hidden />
                                {t('deals.openIn', { tool })}
                            </a>
                        </Button>
                    ) : null}
                </div>
            ) : (
                <div className="flex flex-wrap items-end gap-3">
                    <label className="grid gap-2 text-sm font-medium">
                        {t('deals.fields.stage')}
                        <select
                            className={nativeSelect}
                            value={deal.stage}
                            onChange={(e) =>
                                onMove(e.target.value as DealStage)
                            }
                        >
                            {STAGES.map((s) => (
                                <option key={s} value={s}>
                                    {t(`deals.stages.${s}`)}
                                </option>
                            ))}
                        </select>
                    </label>
                    <Button variant="outline" onClick={onEdit}>
                        <Pencil aria-hidden />
                        {t('deals.edit')}
                    </Button>
                    <Button variant="ghost" onClick={onDelete}>
                        <Trash2 aria-hidden />
                        {t('deals.delete')}
                    </Button>
                </div>
            )}

            <section aria-labelledby="deal-dates" className="space-y-2">
                <h3 id="deal-dates" className="font-semibold">
                    {t('deals.dates')}
                </h3>
                {deal.dates.length === 0 ? (
                    <p className="text-body text-muted-foreground">
                        {t('deals.noNext')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border">
                        {deal.dates.map((date) => (
                            <li
                                key={date.id}
                                className="flex items-center gap-2 py-2"
                            >
                                {date.done ? (
                                    <CheckCircle2
                                        aria-hidden
                                        className="size-4 text-reos-tone-success-fg"
                                    />
                                ) : (
                                    <CalendarClock
                                        aria-hidden
                                        className="size-4 text-primary"
                                    />
                                )}
                                <span
                                    className={
                                        date.done
                                            ? 'text-muted-foreground line-through'
                                            : ''
                                    }
                                >
                                    {dateText(date)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {deal.parties.length > 0 ? (
                <section aria-labelledby="deal-parties" className="space-y-2">
                    <h3 id="deal-parties" className="font-semibold">
                        {t('deals.parties')}
                    </h3>
                    <ul className="divide-y divide-border">
                        {deal.parties.map((p, i) => (
                            <li key={`${p.name}-${i}`} className="py-2">
                                <p className="font-medium">{p.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {[p.role, p.email, p.phone]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {deal.files.length > 0 ? (
                <section aria-labelledby="deal-files" className="space-y-2">
                    <h3 id="deal-files" className="font-semibold">
                        {t('deals.files')}
                    </h3>
                    <ul className="divide-y divide-border">
                        {deal.files.map((f, i) => (
                            <li
                                key={`${f.name}-${i}`}
                                className="flex items-center justify-between gap-3 py-2"
                            >
                                <span>{f.name}</span>
                                <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                    {f.status === 'complete' ? (
                                        <CheckCircle2
                                            aria-hidden
                                            className="size-4 text-reos-tone-success-fg"
                                        />
                                    ) : f.status === 'missing' ? (
                                        <CircleAlert
                                            aria-hidden
                                            className="size-4 text-reos-tone-warning-fg"
                                        />
                                    ) : (
                                        <Clock aria-hidden className="size-4" />
                                    )}
                                    {t(`deals.fileStatus.${f.status}`)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
        </div>
    );
}
