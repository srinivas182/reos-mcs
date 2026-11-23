import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CalmDown } from '@/components/licence/calm-down';
import { dayText, type Journey } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { putJson } from '@/lib/content';
import { kit, show } from '@/routes/licence';

/**
 * Exam-day kit (candidate screen 14, A10.3a): when and where, Directions,
 * a checklist, the testing vendor's rules from the back office, readiness,
 * and a two-minute calm-down.
 */
export default function ExamDayPage({ journey }: { journey: Journey }) {
    const { t, i18n } = useTranslation();
    const { exam } = journey;
    const tick = (item: string, done: boolean) =>
        void putJson(kit(item).url, { done }).then(() =>
            router.reload({ only: ['journey'] }),
        );

    return (
        <>
            <Head title={t('licence.kit.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={show().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('licence.title')}
                        </Link>
                    }
                    title={t('licence.kit.title')}
                    description={t('licence.kit.purpose')}
                />

                <section className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                    <p className="text-lg font-semibold">
                        {exam.on
                            ? [dayText(exam.on, i18n.language), exam.time]
                                  .filter(Boolean)
                                  .join(' · ')
                            : t('licence.kit.noDate')}
                    </p>
                    {exam.centre ? (
                        <p className="text-base">{exam.centre}</p>
                    ) : null}
                    <p className="text-base text-muted-foreground">
                        {t('licence.kit.arrive')}
                    </p>
                    {exam.directions_url ? (
                        <Button asChild variant="outline">
                            <a
                                href={exam.directions_url}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <MapPin aria-hidden />
                                {t('licence.kit.directions')}
                            </a>
                        </Button>
                    ) : null}
                </section>

                <fieldset className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                    <legend className="px-1 text-lg font-semibold">
                        {t('licence.kit.checklist')}
                    </legend>
                    {journey.kit.items.map((item) => (
                        <label
                            key={item.key}
                            htmlFor={`kit-${item.key}`}
                            className="flex min-h-12 items-center gap-3 text-base"
                        >
                            <Checkbox
                                id={`kit-${item.key}`}
                                checked={item.done}
                                onCheckedChange={(on) =>
                                    tick(item.key, on === true)
                                }
                            />
                            {t(`licence.kit.items.${item.key}`)}
                        </label>
                    ))}
                </fieldset>

                {journey.kit.rules ? (
                    <section className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="text-lg font-semibold">
                            {t('licence.kit.rules', {
                                vendor: exam.vendor ?? '',
                            })}
                        </h2>
                        <p className="text-base">{journey.kit.rules}</p>
                    </section>
                ) : null}

                <p className="text-base text-muted-foreground">
                    {t('licence.kit.readiness', {
                        national: journey.readiness.national ?? 0,
                        state: journey.readiness.state ?? 0,
                    })}
                </p>

                <CalmDown />
            </div>
        </>
    );
}
