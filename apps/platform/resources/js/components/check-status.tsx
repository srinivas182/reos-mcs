import { CheckCircle2, CircleAlert, Info } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { CheckOutcome, IntegrationCheck } from '@/types';

const tone: Record<CheckOutcome, string> = {
    passed: 'border-reos-tone-success-fg/25 bg-reos-tone-success-bg text-reos-tone-success-fg',
    failed: 'border-reos-tone-danger-fg/25 bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    unverified:
        'border-reos-tone-info-fg/25 bg-reos-tone-info-bg text-reos-tone-info-fg',
};

const icons = { passed: CheckCircle2, failed: CircleAlert, unverified: Info };

/**
 * The latest connection test for a set of keys, in plain words, with when
 * and by whom. Announced politely, so screen-reader users hear the result
 * of pressing "Test connection".
 */
export function CheckStatus({
    check,
    className,
}: {
    check: IntegrationCheck | null;
    className?: string;
}) {
    const { t, i18n } = useTranslation();

    if (check === null) {
        return (
            <p
                aria-live="polite"
                className={cn('text-sm text-muted-foreground', className)}
            >
                {t('integrations.checks.never')}
            </p>
        );
    }

    const Icon = icons[check.outcome];
    const parameters = { ...check.parameters };

    if (typeof parameters.field === 'string') {
        parameters.field = t(
            `integrations.fields.${parameters.field}` as 'integrations.fields.client_id',
        );
    }

    const time = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(check.created_at));

    return (
        <div
            aria-live="polite"
            className={cn(
                'flex gap-3 rounded-reos-md border p-4',
                tone[check.outcome],
                className,
            )}
        >
            <Icon aria-hidden className="mt-0.5 size-5 shrink-0" />
            <div className="space-y-1">
                <p className="font-medium">{t(check.message, parameters)}</p>
                <p className="text-sm opacity-90">
                    {check.trigger === 'scheduled'
                        ? t('integrations.checks.scheduled', { time })
                        : check.checked_by
                          ? t('integrations.checks.checkedBy', {
                                time,
                                name: check.checked_by,
                            })
                          : t('integrations.checks.checkedAt', { time })}
                </p>
            </div>
        </div>
    );
}
