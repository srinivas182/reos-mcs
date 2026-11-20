import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { ApprovalStatus, IntegrationMode } from '@/types';

const modeTone: Record<IntegrationMode, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    sandbox: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    simulator: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    off: 'bg-transparent text-muted-foreground border-border',
};

const approvalTone: Record<ApprovalStatus, string> = {
    approved: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    not_needed: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    applied: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    in_review: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    blocked: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    not_started: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
};

/** How an integration runs: Off, Demo data, Test account or Live. */
export function ModeBadge({
    mode,
    className,
}: {
    mode: IntegrationMode;
    className?: string;
}) {
    const { t } = useTranslation();

    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', modeTone[mode], className)}
        >
            {t(`integrations.mode.${mode}`)}
        </Badge>
    );
}

/** Where the vendor approval stands, from the integrations register. */
export function ApprovalBadge({ status }: { status: ApprovalStatus }) {
    const { t } = useTranslation();

    return (
        <Badge
            variant="outline"
            className={cn('border-transparent', approvalTone[status])}
        >
            {t('integrations.approval.label')}:{' '}
            {t(`integrations.approval.${status}`)}
        </Badge>
    );
}
