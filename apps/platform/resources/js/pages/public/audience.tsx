import { Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Audience } from '@/components/public-header';
import { Button } from '@/components/ui/button';
import { liveStateWording } from '@/lib/live-states';
import { getStarted, login, packages, signUp } from '@/routes';
import { show as startTenant } from '@/routes/start';

type Props = { audience: Audience; liveStates: string[] };

/**
 * Where each audience's main button leads. Associations join with the REOS
 * team, so their page has Sign in only.
 */
const signUpPath = {
    candidates: 'become_agent',
    agents: 'licensed_agent',
    brokerages: 'broker',
    schools: 'instructor',
    'service-providers': 'provider',
} as const;

type SignUpAudience = keyof typeof signUpPath;

const hasSignUp = (audience: Audience): audience is SignUpAudience =>
    audience in signUpPath;

/**
 * One audience's landing page (web journey screen 1, "Pick a role → that
 * role's landing page and sign-up"): what REOS does for them, then sign-up.
 */
export default function AudiencePage({ audience, liveStates }: Props) {
    const { t, i18n } = useTranslation();
    const state = liveStateWording(liveStates, i18n.language).single;
    const key = `site.audience.${audience}` as const;
    const path = hasSignUp(audience) ? signUpPath[audience] : null;
    const note = t(`${key}.note`, { defaultValue: '' });

    return (
        <>
            <Head title={t(`site.nav.${audience}`)}>
                <meta name="description" content={t(`${key}.intro`)} />
            </Head>
            <div className="mx-auto max-w-3xl space-y-8 px-4 py-12 md:px-8 lg:py-20">
                <header className="space-y-4">
                    <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                        {t(`${key}.eyebrow`)}
                    </p>
                    <h1 className="text-4xl leading-tight font-bold tracking-tight text-balance text-foreground md:text-5xl">
                        {audience === 'service-providers' && !state
                            ? t('site.audience.service-providers.titleAny')
                            : t(`${key}.title`, { state: state ?? '' })}
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        {t(`${key}.intro`)}
                    </p>
                </header>
                <ul className="space-y-3 rounded-reos-lg border border-border bg-card p-6">
                    {(['p1', 'p2', 'p3'] as const).map((point) => (
                        <li key={point} className="flex gap-3 text-foreground">
                            <Check
                                aria-hidden
                                className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                            />
                            {t(`${key}.points.${point}`)}
                        </li>
                    ))}
                </ul>
                {note ? <p className="text-muted-foreground">{note}</p> : null}
                <div className="flex flex-col gap-3 sm:flex-row">
                    {hasSignUp(audience) && path ? (
                        <Button asChild>
                            <Link
                                href={
                                    path === 'become_agent'
                                        ? getStarted()
                                        : signUp(path)
                                }
                            >
                                {t(`site.audience.${audience}.cta`)}
                            </Link>
                        </Button>
                    ) : null}
                    {audience === 'brokerages' || audience === 'schools' ? (
                        <Button asChild variant="outline">
                            <Link
                                href={startTenant(
                                    audience === 'schools'
                                        ? 'school'
                                        : 'brokerage',
                                )}
                            >
                                {t(
                                    `signup.${audience === 'schools' ? 'school' : 'brokerage'}.cta`,
                                )}
                            </Link>
                        </Button>
                    ) : null}
                    {audience === 'candidates' ? (
                        <Button asChild variant="outline">
                            <Link href={packages()}>
                                {t('site.packages.see')}
                            </Link>
                        </Button>
                    ) : null}
                    <Button asChild variant={path ? 'outline' : 'default'}>
                        <Link href={login()}>
                            {path
                                ? t('site.audience.signIn')
                                : t('site.nav.signIn')}
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
