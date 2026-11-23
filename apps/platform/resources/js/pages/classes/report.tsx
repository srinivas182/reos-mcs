import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ClassReportView } from '@/components/classes/class-report';
import type { ClassReport } from '@/components/classes/types';
import { PageHeader } from '@/components/page-header';
import { postJson } from '@/lib/content';
import { index } from '@/routes/classes';
import { decide, send } from '@/routes/classes/report';

/**
 * The CE class report (A10.2c; Web App Journey w505, instructor i13): who
 * earned CE by the approval's rules, borderline cases to decide with a
 * reason, and Confirm and send to school.
 */
export default function ClassReportPage({ report }: { report: ClassReport }) {
    const { t, i18n } = useTranslation();
    const [error, setError] = useState<string | null>(null);
    const id = report.class.id;

    const run = async (url: string, body: unknown = {}) => {
        try {
            await postJson(url, body);
            setError(null);
            router.reload({ only: ['report'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    const when = new Date(report.class.starts_at).toLocaleDateString(
        i18n.language,
        { weekday: 'short', month: 'short', day: 'numeric' },
    );

    return (
        <>
            <Head title={t('classes.report.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={index().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('classes.title')}
                        </Link>
                    }
                    title={t('classes.report.title')}
                    description={`${report.class.title} · ${report.class.course} · ${when}`}
                />
                <ClassReportView
                    report={report}
                    error={error}
                    onDecide={(user, accept, reason) =>
                        void run(decide(id).url, {
                            user,
                            decision: accept ? 'accepted' : 'rejected',
                            reason,
                        })
                    }
                    onSend={() => void run(send(id).url)}
                />
            </div>
        </>
    );
}
