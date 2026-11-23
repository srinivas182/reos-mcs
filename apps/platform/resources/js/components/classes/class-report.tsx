import { CheckCircle2, CircleAlert, CircleX, Send } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ClassReport, ReportStudent } from '@/components/classes/types';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

function Facts({ student }: { student: ReportStudent }) {
    const { t } = useTranslation();

    return (
        <p className="text-sm text-muted-foreground">
            {[
                t('classes.report.present', {
                    count: student.minutes_present,
                }),
                t('classes.report.checks', {
                    answered: student.checks_answered,
                    total: student.checks_total,
                }),
                ...student.reasons.map((r) => t(`classes.report.reasons.${r}`)),
            ].join(' · ')}
        </p>
    );
}

/** A borderline case: accept or reject it with a reason (i13). */
function ReviewCase({
    student,
    canDecide,
    onDecide,
}: {
    student: ReportStudent;
    canDecide: boolean;
    onDecide: (user: number, accept: boolean, reason: string) => void;
}) {
    const { t } = useTranslation();
    const [reason, setReason] = useState(student.decision_reason ?? '');
    const id = `reason-${student.user_id}`;

    return (
        <li className="space-y-3 rounded-reos-md border border-border bg-card p-4">
            <div className="space-y-1">
                <h3 className="text-base font-semibold">{student.name}</h3>
                <Facts student={student} />
                {student.decision ? (
                    <p className="text-base">
                        {t(`classes.report.decided.${student.decision}`)}
                        {student.decision_reason
                            ? `: ${student.decision_reason}`
                            : ''}
                    </p>
                ) : null}
            </div>
            {canDecide ? (
                <div className="space-y-2">
                    <Label htmlFor={id}>{t('classes.report.reason')}</Label>
                    <Textarea
                        id={id}
                        rows={2}
                        maxLength={500}
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            disabled={reason.trim().length < 3}
                            onClick={() =>
                                onDecide(student.user_id, true, reason.trim())
                            }
                        >
                            {t('classes.report.accept')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={reason.trim().length < 3}
                            onClick={() =>
                                onDecide(student.user_id, false, reason.trim())
                            }
                        >
                            {t('classes.report.reject')}
                        </Button>
                    </div>
                </div>
            ) : null}
        </li>
    );
}

/**
 * The CE class report (A10.2c; instructor i13, web w505): joined, earned
 * CE, didn't; borderline cases to accept or reject with a reason; Confirm
 * and send to school. The school's admins see it read-only.
 */
export function ClassReportView({
    report,
    onDecide,
    onSend,
    error,
}: {
    report: ClassReport;
    onDecide: (user: number, accept: boolean, reason: string) => void;
    onSend: () => void;
    error?: string | null;
}) {
    const { t } = useTranslation();
    const review = report.students.filter((s) => s.outcome === 'review');
    const earned = report.students.filter((s) => s.outcome === 'earned');
    const didnt = report.students.filter((s) => s.outcome === 'not_earned');
    const tiles = [
        {
            label: t('classes.report.counts.joined'),
            value: report.counts.joined,
        },
        {
            label: t('classes.report.counts.earned'),
            value: report.counts.earned,
        },
        {
            label: t('classes.report.counts.notEarned'),
            value: report.counts.not_earned,
        },
        {
            label: t('classes.report.counts.review'),
            value: report.counts.review,
        },
    ];

    return (
        <div className="space-y-6">
            <p className="text-base text-muted-foreground">
                {t('classes.report.rules', {
                    percent: report.rules.presence_percent,
                    minutes: report.rules.required_minutes,
                    checks: report.rules.checks,
                })}
                {report.class.approval
                    ? ` ${t('classes.report.approval', { number: report.class.approval })}`
                    : ''}
            </p>

            <dl className="grid grid-cols-2 gap-3 md:grid-cols-4">
                {tiles.map((tile) => (
                    <div
                        key={tile.label}
                        className="rounded-reos-lg border border-border bg-card p-4"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {tile.label}
                        </dt>
                        <dd className="text-title font-semibold tabular-nums">
                            {tile.value}
                        </dd>
                    </div>
                ))}
            </dl>

            {report.status === 'sent' ? (
                <Alert>
                    <CheckCircle2 aria-hidden />
                    <AlertDescription className="text-base">
                        {t('classes.report.sent', {
                            name: report.sent_by ?? '',
                            date: report.sent_at
                                ? new Date(report.sent_at).toLocaleString()
                                : '',
                        })}
                    </AlertDescription>
                </Alert>
            ) : null}

            {review.length > 0 ? (
                <section aria-labelledby="report-review" className="space-y-3">
                    <h2
                        id="report-review"
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <CircleAlert aria-hidden className="size-5" />
                        {t('classes.report.toReview')}
                    </h2>
                    <ul className="space-y-3">
                        {review.map((s) => (
                            <ReviewCase
                                key={s.user_id}
                                student={s}
                                canDecide={report.can_decide}
                                onDecide={onDecide}
                            />
                        ))}
                    </ul>
                </section>
            ) : null}

            <section aria-labelledby="report-earned" className="space-y-3">
                <h2
                    id="report-earned"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <CheckCircle2 aria-hidden className="size-5" />
                    {t('classes.report.earned')}
                </h2>
                {earned.length === 0 ? (
                    <p className="text-base text-muted-foreground">
                        {t('classes.report.noneEarned')}
                    </p>
                ) : (
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {earned.map((s) => (
                            <li key={s.user_id} className="space-y-1 p-4">
                                <p className="text-base font-medium">
                                    {s.name}
                                </p>
                                <Facts student={s} />
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {didnt.length > 0 ? (
                <section aria-labelledby="report-didnt" className="space-y-3">
                    <h2
                        id="report-didnt"
                        className="flex items-center gap-2 text-lg font-semibold"
                    >
                        <CircleX aria-hidden className="size-5" />
                        {t('classes.report.notEarned')}
                    </h2>
                    <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                        {didnt.map((s) => (
                            <li key={s.user_id} className="space-y-1 p-4">
                                <p className="text-base font-medium">
                                    {s.name}
                                </p>
                                <Facts student={s} />
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}

            {error ? <InputError message={error} /> : null}

            {report.status === 'draft' && report.can_decide ? (
                <div className="space-y-2">
                    {report.counts.undecided > 0 ? (
                        <p className="text-base text-muted-foreground">
                            {t('classes.report.decideFirst', {
                                count: report.counts.undecided,
                            })}
                        </p>
                    ) : null}
                    <Button
                        size="lg"
                        disabled={!report.can_send}
                        onClick={onSend}
                    >
                        <Send aria-hidden />
                        {t('classes.report.send')}
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
