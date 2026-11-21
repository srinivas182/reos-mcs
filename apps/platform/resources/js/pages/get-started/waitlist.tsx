import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Check, Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getStarted } from '@/routes';
import { join } from '@/routes/get-started/waitlist';

type StateRow = { code: string; name: string; live: boolean; waiting: number };
type Props = { state: StateRow; live: StateRow[] };

/** "REOS isn't in Louisiana yet" (candidate journey screen 3, on the web). */
export default function Waitlist({ state, live }: Props) {
    const { t } = useTranslation();
    const { flash, auth } = usePage().props;
    const joined = flash.joined;
    const firstLive = live[0];

    return (
        <>
            <Head
                title={t('onboarding.waitlist.title', { state: state.name })}
            />
            <div className="mx-auto max-w-xl space-y-6 px-4 py-10 text-center md:py-16">
                <span className="mx-auto flex size-16 items-center justify-center rounded-reos-lg bg-reos-tone-info-bg text-reos-tone-info-fg">
                    <Globe aria-hidden className="size-8" />
                </span>
                <h1 className="text-3xl font-bold tracking-tight text-foreground">
                    {t('onboarding.waitlist.title', { state: state.name })}
                </h1>
                <p className="text-lg text-muted-foreground">
                    {t('onboarding.waitlist.body', { state: state.name })}
                </p>
                <div className="space-y-2 rounded-reos-lg border border-border bg-card p-5 text-left">
                    <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                        {t('onboarding.waitlist.youGet')}
                    </p>
                    <ul className="space-y-2">
                        {(['early', 'credits', 'say'] as const).map((item) => (
                            <li
                                key={item}
                                className="flex gap-3 text-foreground"
                            >
                                <Check
                                    aria-hidden
                                    className="mt-0.5 size-5 shrink-0 text-reos-tone-success-fg"
                                />
                                {t(`onboarding.waitlist.${item}`)}
                            </li>
                        ))}
                    </ul>
                </div>
                {state.waiting > 0 ? (
                    <p className="text-sm text-muted-foreground">
                        {t('onboarding.waitlist.waiting', {
                            count: state.waiting,
                            state: state.name,
                        })}
                    </p>
                ) : null}

                {joined ? (
                    <p
                        role="status"
                        className="rounded-reos-md bg-reos-tone-success-bg p-4 font-semibold text-reos-tone-success-fg"
                    >
                        {t('onboarding.waitlist.joined', { state: state.name })}
                    </p>
                ) : (
                    <Form
                        {...join.form(state.code)}
                        className="space-y-4 text-left"
                    >
                        {({ processing, errors }) => (
                            <>
                                {auth.user ? null : (
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">
                                            {t('site.getStarted.waitlistEmail')}
                                        </Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            autoComplete="email"
                                            required
                                            aria-describedby="email-help"
                                        />
                                        <p
                                            id="email-help"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t(
                                                'site.getStarted.waitlistEmailHelp',
                                            )}
                                        </p>
                                        <InputError message={errors.email} />
                                    </div>
                                )}
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {t('onboarding.waitlist.join', {
                                        state: state.name,
                                    })}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                {firstLive ? (
                    <Button asChild variant="outline" className="w-full">
                        <Link href={getStarted()}>
                            {t('onboarding.waitlist.texasInstead')}
                        </Link>
                    </Button>
                ) : null}
            </div>
        </>
    );
}
