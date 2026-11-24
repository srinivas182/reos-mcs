import {
    CheckCircle2,
    Circle,
    CircleDashed,
    PlugZap,
    XCircle,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import type { Reporting, StepState } from './types';

const ICON: Record<StepState, LucideIcon> = {
    done: CheckCircle2,
    waiting: Circle,
    pending: CircleDashed,
    rejected: XCircle,
    not_connected: PlugZap,
};
const TONE: Record<StepState, string> = {
    done: 'text-reos-tone-success-fg',
    waiting: 'text-muted-foreground',
    pending: 'text-reos-tone-info-fg',
    rejected: 'text-reos-tone-danger-fg',
    not_connected: 'text-muted-foreground',
};

/**
 * CE reporting steps for one REOS completion (A11.3; agent screen 104):
 * completed, final passed, reported to TREC (with the reference), the
 * Texas REALTORS® member record and the certificate.
 */
export function ReportingSteps({ reporting }: { reporting: Reporting }) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2">
            <p className="text-base font-semibold">
                {t('ce.wallet.steps.title')}:{' '}
                {t(`ce.wallet.steps.status.${reporting.status}`)}
            </p>
            <ol className="space-y-2">
                {reporting.steps.map((step) => {
                    const Icon = ICON[step.state];
                    return (
                        <li
                            key={step.code}
                            className="flex items-start gap-2 text-base"
                        >
                            <Icon
                                aria-hidden
                                className={cn(
                                    'mt-0.5 size-5 shrink-0',
                                    TONE[step.state],
                                )}
                            />
                            <span className="flex-1">
                                {t(`ce.wallet.steps.codes.${step.code}`)}
                                {step.code === 'reported' && reporting.reference
                                    ? ` · ${t('ce.wallet.steps.reference', { reference: reporting.reference })}`
                                    : null}
                                {step.code === 'reported' && reporting.reason
                                    ? ` · ${t('ce.wallet.steps.reason', { reason: reporting.reason })}`
                                    : null}
                            </span>
                            <span className="text-muted-foreground">
                                {t(`ce.wallet.steps.states.${step.state}`)}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
