import { Head } from '@inertiajs/react';
import { CheckCircle2, CircleAlert } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

/**
 * The public status page (A6.1, "Open status page" on screen 58): whether
 * REOS is working now, each part, and uptime over 30 and 90 days.
 */
export default function Status({
    ok,
    parts,
    checked_at,
    uptime,
}: {
    ok: boolean | null;
    parts: Record<string, boolean>;
    checked_at: string | null;
    uptime: { '30': number | null; '90': number | null };
}) {
    const { t, i18n } = useTranslation();

    return (
        <>
            <Head title={t('status.title')} />
            <div className="mx-auto max-w-3xl space-y-6 px-4 py-12">
                <h1 className="text-title font-semibold">
                    {t('status.title')}
                </h1>
                <p
                    className={cn(
                        'flex items-center gap-3 rounded-reos-lg p-4 text-lg font-semibold',
                        ok === false
                            ? 'bg-reos-tone-danger-bg text-reos-tone-danger-fg'
                            : 'bg-reos-tone-success-bg text-reos-tone-success-fg',
                    )}
                >
                    {ok === false ? (
                        <CircleAlert aria-hidden className="size-6" />
                    ) : (
                        <CheckCircle2 aria-hidden className="size-6" />
                    )}
                    {ok === false ? t('status.problem') : t('status.allGood')}
                </p>
                <ul className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                    {Object.entries(parts).map(([part, good]) => (
                        <li
                            key={part}
                            className="flex items-center justify-between gap-3 px-4 py-3"
                        >
                            <span>
                                {t(`status.parts.${part as 'database'}`)}
                            </span>
                            <span
                                className={
                                    good
                                        ? 'text-reos-tone-success-fg'
                                        : 'text-reos-tone-danger-fg'
                                }
                            >
                                {good ? t('status.working') : t('status.down')}
                            </span>
                        </li>
                    ))}
                </ul>
                <dl className="grid gap-4 sm:grid-cols-2">
                    {(['30', '90'] as const).map((days) => (
                        <div
                            key={days}
                            className="rounded-reos-lg border border-border bg-card p-4"
                        >
                            <dt className="text-muted-foreground">
                                {t('status.uptime', { days })}
                            </dt>
                            <dd className="text-title font-semibold tabular-nums">
                                {uptime[days] === null
                                    ? '—'
                                    : `${uptime[days]}%`}
                            </dd>
                        </div>
                    ))}
                </dl>
                {checked_at ? (
                    <p className="text-sm text-muted-foreground">
                        {t('status.checked', {
                            time: new Date(checked_at).toLocaleString(
                                i18n.language,
                                {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                },
                            ),
                        })}
                    </p>
                ) : null}
            </div>
        </>
    );
}
