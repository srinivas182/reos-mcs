import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { RolePicker } from '@/components/role-picker';

/**
 * reos.com home page (web journey screen 1): what REOS is, the role picker,
 * a sample AI lesson and key numbers.
 */
export default function Welcome() {
    const { liveStates } = usePage().props;
    const { t } = useTranslation();
    const state = liveStates[0] ?? '';

    return (
        <>
            <Head title={t('site.home.title')}>
                <meta name="description" content={t('site.home.intro')} />
            </Head>
            <div className="mx-auto grid max-w-7xl gap-12 px-4 py-12 md:px-8 lg:grid-cols-2 lg:py-20">
                <div className="space-y-8">
                    {state ? (
                        <p className="inline-flex rounded-reos-pill bg-reos-tone-info-bg px-3 py-1 text-sm font-semibold text-reos-tone-info-fg">
                            {t('site.home.badge', { state })}
                        </p>
                    ) : null}
                    <h1 className="text-4xl leading-[1.05] font-bold tracking-tight text-balance text-foreground md:text-6xl">
                        {t('site.home.headline', { state })}
                    </h1>
                    <p className="max-w-xl text-lg text-muted-foreground">
                        {t('site.home.intro')}
                    </p>
                    <RolePicker state={state} />
                </div>

                <div className="space-y-4 lg:pt-8">
                    <figure className="overflow-hidden rounded-reos-lg border border-border bg-card">
                        <div className="relative aspect-video overflow-hidden bg-reos-hero-bg">
                            <span className="absolute top-4 left-4 rounded-reos-pill bg-reos-hero-text/10 px-3 py-1 text-sm font-semibold text-reos-hero-text">
                                {t('site.home.lesson.tag', { state })}
                            </span>
                            <span
                                aria-hidden
                                className="absolute bottom-0 left-1/2 size-36 -translate-x-1/2 translate-y-1/4 rounded-full bg-reos-hero-muted/60"
                            />
                        </div>
                        <figcaption className="px-5 py-4">
                            <p className="font-semibold text-foreground">
                                {t('site.home.lesson.title')}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('site.home.lesson.meta')}
                            </p>
                        </figcaption>
                    </figure>
                    <dl
                        aria-label={t('site.home.stats.label')}
                        className="grid grid-cols-3 gap-4"
                    >
                        {(
                            [
                                ['agentsValue', 'agents'],
                                ['aiValue', 'ai'],
                                ['appValue', 'app'],
                            ] as const
                        ).map(([value, label]) => (
                            <div
                                key={label}
                                className="flex flex-col-reverse rounded-reos-lg border border-border bg-card px-5 py-4"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {t(`site.home.stats.${label}`, { state })}
                                </dt>
                                <dd className="text-2xl font-bold text-foreground">
                                    {t(`site.home.stats.${value}`)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </div>
        </>
    );
}
