import { Head, router } from '@inertiajs/react';
import { CheckCircle2, LifeBuoy } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { approve, decline, revoke } from '@/routes/support-access';
import type { SupportGrant } from '@/types';

/**
 * Support sign-in request (A4.1c), for the tenant's owner: who asks, why,
 * exactly what support can and can't do, then Approve or Decline. While
 * access is open, End support access.
 */
export default function SupportAccessShow({
    grant,
    twoStep = true,
}: {
    grant: SupportGrant;
    /** Approving needs two-step; declining never does. */
    twoStep?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const time = (iso: string | null) =>
        iso
            ? new Intl.DateTimeFormat(i18n.language, {
                  timeStyle: 'short',
              }).format(new Date(iso))
            : '';
    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    const actions =
        grant.status === 'pending' ? (
            <>
                <Button
                    variant="outline"
                    onClick={() => post(decline(grant.id).url)}
                >
                    {t('support.page.decline')}
                </Button>
                <Button
                    disabled={!twoStep}
                    onClick={() => post(approve(grant.id).url)}
                >
                    {t('support.page.approve')}
                </Button>
            </>
        ) : grant.status === 'approved' || grant.status === 'active' ? (
            <Button
                variant="destructive"
                onClick={() => post(revoke(grant.id).url)}
            >
                {t('support.page.revoke')}
            </Button>
        ) : null;

    return (
        <>
            <Head title={t('support.page.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('support.page.title')}
                    description={t('support.page.purpose', {
                        tenant: grant.tenant,
                    })}
                    actions={actions}
                />

                <p
                    role="status"
                    className="flex items-center gap-3 rounded-reos-md bg-reos-tone-info-bg p-4 text-body font-semibold text-reos-tone-info-fg"
                >
                    <LifeBuoy aria-hidden className="size-5 shrink-0" />
                    {t(`support.page.statuses.${grant.status}`, {
                        hours: grant.answer_hours,
                        person: grant.decided_by ?? '',
                        time: time(grant.ends_at),
                    })}
                </p>

                {grant.status === 'pending' && !twoStep ? (
                    <p className="max-w-4xl rounded-reos-md bg-reos-tone-warning-bg p-4 text-body text-reos-tone-warning-fg">
                        {t('support.page.twoStepNote')}
                    </p>
                ) : null}

                <div className="grid max-w-4xl gap-4 md:grid-cols-2">
                    <section
                        className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                        aria-labelledby="support-request"
                    >
                        <h2 id="support-request" className="sr-only">
                            {t('support.page.title')}
                        </h2>
                        <div>
                            <h3 className="text-sm text-muted-foreground">
                                {t('support.page.who')}
                            </h3>
                            <p className="font-semibold">{grant.requester}</p>
                        </div>
                        <div>
                            <h3 className="text-sm text-muted-foreground">
                                {t('support.page.why')}
                            </h3>
                            <p className="text-body">{grant.reason}</p>
                        </div>
                    </section>
                    <section
                        className="rounded-reos-lg border border-border bg-card p-5"
                        aria-labelledby="support-terms"
                    >
                        <h2 id="support-terms" className="mb-3 font-semibold">
                            {t('support.page.how')}
                        </h2>
                        <ul className="space-y-3">
                            {(['t1', 't2', 't3', 't4'] as const).map((key) => (
                                <li key={key} className="flex gap-3 text-body">
                                    <CheckCircle2
                                        aria-hidden
                                        className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                    />
                                    {t(`support.page.terms.${key}`, {
                                        tenant: grant.tenant,
                                        minutes: grant.minutes,
                                    })}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}
