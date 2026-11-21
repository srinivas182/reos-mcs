import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { TenantStatus } from '@/types';

const tone: Record<TenantStatus, string> = {
    active: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    trial: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    suspended: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    archived: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/** A tenant's status (screens 59 and 60): Live, Trial, Suspended or Archived. */
export function TenantStatusBadge({ status }: { status: TenantStatus }) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                tone[status],
            )}
        >
            {t(`tenants.statuses.${status}`)}
        </span>
    );
}
