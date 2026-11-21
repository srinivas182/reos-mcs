import { Head } from '@inertiajs/react';
import { Calendar, FlaskConical, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';
import { decide } from '@/routes/connections/simulator';

type Props = {
    integrationId: string;
    integration: string;
    csrfToken: string;
    account: string;
    state: string;
    codeChallenge: string;
    redirectUri: string;
};

/**
 * The demo-data stand-in for a tool's sign-in (Simulator mode, B3.1). It is
 * plainly a REOS demo page, never a copy of the tool's own page, and it
 * answers the way the tool would: a code on Allow, access_denied otherwise.
 */
export default function SimulatorSignIn({
    integrationId,
    integration,
    csrfToken,
    account,
    state,
    codeChallenge,
    redirectUri,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('connections.simulator.title')} />
            <main className="flex min-h-dvh items-center justify-center bg-background px-4 py-10">
                <div className="w-full max-w-md space-y-6 rounded-reos-lg border border-border bg-card p-8">
                    <div className="flex items-center gap-3">
                        <BrandMark className="size-10" />
                        <span className="inline-flex items-center gap-1.5 rounded-reos-pill bg-reos-tone-warning-bg px-3 py-1 text-sm font-semibold text-reos-tone-warning-fg">
                            <FlaskConical aria-hidden className="size-4" />
                            {t('connections.simulator.eyebrow')}
                        </span>
                    </div>
                    <header className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('connections.simulator.title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('connections.simulator.body', {
                                tool: integration,
                            })}
                        </p>
                        <p className="font-semibold text-foreground">
                            {t('connections.simulator.account', { account })}
                        </p>
                    </header>
                    <section className="space-y-3">
                        <h2 className="font-semibold text-foreground">
                            {t('connections.simulator.wants')}
                        </h2>
                        <p className="flex items-center gap-3 text-foreground">
                            <Calendar
                                aria-hidden
                                className="size-5 text-primary"
                            />
                            {t('connections.simulator.readCalendar')}
                        </p>
                        <p className="flex items-center gap-3 text-muted-foreground">
                            <ShieldCheck
                                aria-hidden
                                className="size-5 text-reos-tone-success-fg"
                            />
                            {t('connections.simulator.never')}
                        </p>
                    </section>
                    {/* A full-page post: the answer redirects to the callback address. */}
                    <form
                        method="post"
                        action={decide.url(integrationId)}
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        <input type="hidden" name="_token" value={csrfToken} />
                        <input type="hidden" name="state" value={state} />
                        <input
                            type="hidden"
                            name="code_challenge"
                            value={codeChallenge}
                        />
                        <input
                            type="hidden"
                            name="redirect_uri"
                            value={redirectUri}
                        />
                        <Button
                            type="submit"
                            name="allow"
                            value="0"
                            variant="outline"
                        >
                            {t('connections.simulator.deny')}
                        </Button>
                        <Button type="submit" name="allow" value="1">
                            {t('connections.simulator.allow')}
                        </Button>
                    </form>
                </div>
            </main>
        </>
    );
}
