import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Printer } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ReportingSteps } from '@/components/ce/reporting-steps';
import type { Certificate } from '@/components/ce/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as wallet } from '@/routes/ce';

const day = (iso: string, locale: string) =>
    new Intl.DateTimeFormat(locale, { dateStyle: 'long' }).format(
        new Date(`${iso.slice(0, 10)}T12:00:00`),
    );

/**
 * A CE certificate (A11.3; agent screens 103 and 104 in the web shell):
 * the certificate to print or save as PDF once TREC has the completion,
 * and the reporting steps. Always available, even if the membership lapses.
 */
export default function CertificatePage({
    certificate,
}: {
    certificate: Certificate;
}) {
    const { t, i18n } = useTranslation();
    const rows: [string, string | null][] = [
        [t('ce.wallet.certificate.holder'), certificate.holder],
        [
            t('ce.wallet.certificate.licence'),
            certificate.licence
                ? `${certificate.state_code} ${certificate.licence}`
                : null,
        ],
        [t('ce.wallet.certificate.course'), certificate.title],
        [t('ce.wallet.certificate.courseNumber'), certificate.course_number],
        [t('ce.wallet.certificate.hours'), String(certificate.hours)],
        [
            t('ce.wallet.certificate.completed'),
            day(certificate.completed_on, i18n.language),
        ],
        [t('ce.wallet.certificate.provider'), certificate.provider],
    ];

    return (
        <>
            <Head title={t('ce.wallet.certificate.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('ce.wallet.certificate.title')}
                    description={certificate.title}
                    back={
                        <Button
                            asChild
                            variant="ghost"
                            className="print:hidden"
                        >
                            <Link href={wallet().url}>
                                <ArrowLeft aria-hidden />
                                {t('ce.wallet.certificate.back')}
                            </Link>
                        </Button>
                    }
                    actions={
                        certificate.issued ? (
                            <Button
                                variant="outline"
                                className="print:hidden"
                                onClick={() => window.print()}
                            >
                                <Printer aria-hidden />
                                {t('ce.wallet.certificate.print')}
                            </Button>
                        ) : undefined
                    }
                />
                <section
                    aria-labelledby="certificate"
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-8 print:border-0"
                >
                    <h2 id="certificate" className="text-2xl font-semibold">
                        {t('ce.wallet.certificate.heading')}
                    </h2>
                    <dl className="grid gap-3 sm:grid-cols-2">
                        {rows
                            .filter(
                                (row): row is [string, string] =>
                                    row[1] !== null,
                            )
                            .map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-base text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="text-lg font-semibold">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                    </dl>
                    {certificate.reporting?.reference ? (
                        <p className="text-base">
                            {t('ce.wallet.steps.reference', {
                                reference: certificate.reporting.reference,
                            })}
                        </p>
                    ) : null}
                    {!certificate.issued ? (
                        <p className="text-base text-muted-foreground">
                            {certificate.reporting
                                ? t('ce.wallet.certificate.notIssued')
                                : t('ce.wallet.certificate.selfReported')}
                        </p>
                    ) : null}
                </section>
                {certificate.reporting ? (
                    <section className="rounded-reos-lg border border-border bg-card p-5 print:hidden">
                        <ReportingSteps reporting={certificate.reporting} />
                        <p className="mt-3 text-base text-muted-foreground">
                            {t('ce.wallet.steps.memberNote')}
                        </p>
                    </section>
                ) : null}
            </div>
        </>
    );
}
