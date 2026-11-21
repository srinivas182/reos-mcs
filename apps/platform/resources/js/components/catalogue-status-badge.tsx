import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { CatalogueStatus } from '@/types';

const tone: Record<CatalogueStatus, string> = {
    enabled: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    beta: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    coming_soon: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    paused: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    retired: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

/**
 * A tool's catalogue status (web journey screen 64). `audience="people"`
 * uses the words people see: "Temporarily unavailable" rather than "Paused".
 */
export function CatalogueStatusBadge({
    status,
    audience = 'admin',
}: {
    status: CatalogueStatus;
    audience?: 'admin' | 'people';
}) {
    const { t } = useTranslation();

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                tone[status],
            )}
        >
            {audience === 'people'
                ? t(`catalogue.peopleStatuses.${status}`)
                : t(`catalogue.statuses.${status}`)}
        </span>
    );
}
