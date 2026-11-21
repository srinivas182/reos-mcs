import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type Props = {
    onboarding: {
        exam_month: string | null;
        minutes_per_day: number | null;
        plan: { days_to_exam: number | null; qualifying_hours: number | null };
    };
    state: string | null;
};

/**
 * "Your plan is ready" (candidate journey screen 9, on the web): days to the
 * target exam and the four steps to a license.
 */
export default function PlanReady({ onboarding }: Props) {
    const { t, i18n } = useTranslation();
    const { days_to_exam: days, qualifying_hours: hours } = onboarding.plan;
    const minutes = onboarding.minutes_per_day ?? 20;
    const month = onboarding.exam_month
        ? new Intl.DateTimeFormat(i18n.language, {
              month: 'long',
              year: 'numeric',
          }).format(new Date(`${onboarding.exam_month}-15T12:00:00`))
        : null;
    const steps = [
        {
            title: t('onboarding.plan.steps.courses'),
            detail: hours
                ? t('onboarding.plan.steps.coursesDetail', { hours })
                : t('onboarding.plan.steps.coursesDetailNoHours'),
        },
        {
            title: t('onboarding.plan.steps.prep'),
            detail: t('onboarding.plan.steps.prepDetail'),
        },
        {
            title: t('onboarding.plan.steps.exam'),
            detail: t('onboarding.plan.steps.examDetail'),
        },
        {
            title: t('onboarding.plan.steps.license'),
            detail: t('onboarding.plan.steps.licenseDetail'),
        },
    ];

    return (
        <>
            <Head title={t('site.getStarted.planTitle')} />
            <div className="mx-auto max-w-2xl space-y-8 px-4 py-10 md:py-16">
                <header className="space-y-3">
                    <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                        {t('onboarding.plan.eyebrow')}
                    </p>
                    <h1 className="text-4xl font-bold tracking-tight text-foreground">
                        {month
                            ? t('onboarding.plan.titleMonth', { month })
                            : t('onboarding.plan.titleNotSure')}
                    </h1>
                </header>

                <div className="flex items-center gap-6 rounded-reos-lg bg-reos-hero-bg p-6 text-reos-hero-text">
                    {days !== null ? (
                        <div className="flex size-24 shrink-0 flex-col items-center justify-center rounded-full border-[6px] border-reos-success">
                            <span className="text-3xl font-bold">{days}</span>
                            <span className="text-sm text-reos-hero-muted">
                                {t('onboarding.plan.days')}
                            </span>
                        </div>
                    ) : null}
                    <div className="space-y-1">
                        {month ? (
                            <>
                                <p className="text-reos-hero-muted">
                                    {t('onboarding.plan.targetExam')}
                                </p>
                                <p className="text-2xl font-bold">{month}</p>
                            </>
                        ) : null}
                        <p className="text-reos-hero-muted">
                            {hours
                                ? t('onboarding.plan.perDay', {
                                      minutes,
                                      hours,
                                  })
                                : t('onboarding.plan.perDayNoHours', {
                                      minutes,
                                  })}
                        </p>
                    </div>
                </div>

                <ol className="divide-y divide-border rounded-reos-lg border border-border bg-card">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="flex items-center gap-4 px-5 py-4"
                        >
                            <span
                                aria-hidden
                                className="flex size-10 shrink-0 items-center justify-center rounded-reos-md bg-reos-tone-info-bg font-semibold text-reos-tone-info-fg"
                            >
                                {index + 1}
                            </span>
                            <span>
                                <span className="block font-semibold text-foreground">
                                    {step.title}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {step.detail}
                                </span>
                            </span>
                        </li>
                    ))}
                </ol>

                <Button asChild className="w-full">
                    <Link href={dashboard()}>
                        {t('site.getStarted.toDashboard')}
                    </Link>
                </Button>
            </div>
        </>
    );
}
