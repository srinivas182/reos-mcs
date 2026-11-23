import { CalendarClock, FileWarning, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { TOOL_NAMES, type DealCard, type DealDate } from './types';

export const money = (value: number | string, language: string) =>
    new Intl.NumberFormat(language, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 0,
    }).format(Number(value));

/** "Option period ends, Nov 26 (in 3 days)". */
export function useDateText() {
    const { t, i18n } = useTranslation();

    return (date: DealDate) =>
        t('deals.next', {
            name: date.name,
            date: `${new Date(`${date.due_on}T12:00:00`).toLocaleDateString(i18n.language, { month: 'short', day: 'numeric' })} (${t('deals.days', { count: date.days })})`,
        });
}

/**
 * A deal on the board (screen 14): property, side, price and the next
 * deadline; where it comes from, and documents missing.
 */
export function DealCardView({
    deal,
    onOpen,
}: {
    deal: DealCard;
    onOpen: () => void;
}) {
    const { t, i18n } = useTranslation();
    const dateText = useDateText();

    return (
        <button
            type="button"
            onClick={onOpen}
            className="w-full space-y-2 rounded-reos-lg border border-border bg-card p-4 text-left focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <span className="block font-semibold">{deal.property}</span>
            <span className="block text-sm text-muted-foreground">
                {t(`deals.sides.${deal.side}`)}
                {deal.price !== null
                    ? ` · ${money(deal.price, i18n.language)}`
                    : ''}
            </span>
            <span className="flex items-center gap-2 text-sm">
                <CalendarClock aria-hidden className="size-4 text-primary" />
                {deal.next ? dateText(deal.next) : t('deals.noNext')}
            </span>
            {deal.documents.missing > 0 ? (
                <span className="flex items-center gap-2 text-sm text-reos-tone-warning-fg">
                    <FileWarning aria-hidden className="size-4" />
                    {t('deals.missingDocs', { count: deal.documents.missing })}
                </span>
            ) : null}
            <span className="flex items-center gap-2 text-sm text-muted-foreground">
                {deal.read_only ? (
                    <Lock aria-hidden className="size-4" />
                ) : null}
                {deal.read_only
                    ? t('deals.from', {
                          tool: TOOL_NAMES[deal.source] ?? deal.source,
                      })
                    : t('deals.tracked')}
            </span>
        </button>
    );
}
