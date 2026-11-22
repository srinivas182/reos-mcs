import { useTranslation } from 'react-i18next';
import type { CourseStatus } from '@/lib/content';
import { statusTone } from '@/lib/content';
import { cn } from '@/lib/utils';

/**
 * A course's status (A5.3; screen 63): Live, In review, Draft or Hidden.
 * A CE or qualifying course missing an approval shows "Hidden: no approval".
 */
export function CourseStatusBadge({
    status,
    noApproval = false,
}: {
    status: CourseStatus;
    noApproval?: boolean;
}) {
    const { t } = useTranslation();
    const hiddenForApproval = noApproval && status !== 'live';

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-reos-pill px-3 py-1 text-sm font-semibold whitespace-nowrap',
                hiddenForApproval ? statusTone.hidden : statusTone[status],
            )}
        >
            {hiddenForApproval
                ? t('content.status.noApproval')
                : t(`content.status.${status}`)}
        </span>
    );
}
