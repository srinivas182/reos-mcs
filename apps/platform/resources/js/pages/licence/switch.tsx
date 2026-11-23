import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, Building2, KeyRound, User } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { dayText, type SwitchState } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { postJson } from '@/lib/content';
import { dashboard } from '@/routes';
import { offers, show } from '@/routes/licence';
import { check, switchMethod } from '@/routes/licence/licence';
import { index as mentors } from '@/routes/mentors';

/**
 * Enter your licence number (candidate f03) and Licensed: switch to agent
 * (20), A10.3c. Checked against the regulator's record; it must be active
 * and in the account's name; if not yet, REOS checks daily. Then one of
 * three ways in, on the same account.
 */
export default function SwitchPage({ switch: state }: { switch: SwitchState }) {
    const { t, i18n } = useTranslation();
    const [number, setNumber] = useState(state.number ?? '');
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const run = async (url: string, body: unknown, after?: () => void) => {
        setBusy(true);
        try {
            await postJson(url, body);
            setError(null);
            if (after) {
                after();
            } else {
                router.reload({ only: ['switch'] });
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const become = (path: 'broker' | 'association' | 'alone') =>
        void run(
            switchMethod().url,
            { path, code: path === 'association' ? code : null },
            () => router.visit(dashboard().url),
        );

    return (
        <>
            <Head title={t('licence.switch.title')} />
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
                    title={t('licence.switch.title')}
                    description={t('licence.switch.purpose')}
                />

                {state.switched ? (
                    <Alert>
                        <BadgeCheck aria-hidden />
                        <AlertTitle>{t('licence.switch.done')}</AlertTitle>
                        <AlertDescription className="text-base">
                            <Button asChild className="mt-2">
                                <Link href={mentors().url}>
                                    {t('licence.switch.mentors')}
                                </Link>
                            </Button>
                        </AlertDescription>
                    </Alert>
                ) : (
                    <form
                        className="flex flex-wrap items-end gap-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            void run(check().url, { number: number.trim() });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="licence-number">
                                {t('licence.switch.number')}
                            </Label>
                            <Input
                                id="licence-number"
                                className="w-64"
                                inputMode="numeric"
                                value={number}
                                maxLength={40}
                                onChange={(e) => setNumber(e.target.value)}
                            />
                        </div>
                        <Button
                            type="submit"
                            disabled={busy || number.trim().length < 4}
                        >
                            {t('licence.switch.check')}
                        </Button>
                    </form>
                )}
                {error ? <InputError message={error} /> : null}

                {!state.switched &&
                state.check === 'verified' &&
                state.record ? (
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <BadgeCheck
                                aria-hidden
                                className="size-5 text-reos-tone-success-fg"
                            />
                            {t('licence.switch.found')}
                        </h2>
                        <p className="text-base">
                            {[
                                state.record.name,
                                t(
                                    `licence.switch.types.${state.record.type === 'broker' ? 'broker' : 'sales_agent'}`,
                                ),
                                state.record.licensed_on
                                    ? t('licence.switch.issued', {
                                          date: dayText(
                                              state.record.licensed_on,
                                              i18n.language,
                                          ),
                                      })
                                    : null,
                                state.record.sponsor
                                    ? t('licence.switch.sponsor', {
                                          name: state.record.sponsor,
                                      })
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                        <ul className="list-disc space-y-1 pl-5 text-base text-muted-foreground">
                            <li>{t('licence.switch.changes.account')}</li>
                            <li>{t('licence.switch.changes.progress')}</li>
                            <li>{t('licence.switch.changes.mentors')}</li>
                        </ul>
                        <h3 className="text-base font-semibold">
                            {t('licence.switch.ways')}
                        </h3>
                        <div className="grid gap-3 md:grid-cols-3">
                            <div className="space-y-2 rounded-reos-md border border-border p-4">
                                <p className="flex items-center gap-2 font-semibold">
                                    <Building2 aria-hidden className="size-5" />
                                    {t('licence.switch.broker')}
                                </p>
                                {state.broker ? (
                                    <Button
                                        disabled={busy}
                                        onClick={() => become('broker')}
                                    >
                                        {t('licence.switch.join', {
                                            name: state.broker.name,
                                        })}
                                    </Button>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('licence.switch.noBroker')}{' '}
                                        <Link
                                            href={offers().url}
                                            className="text-primary underline-offset-4 hover:underline"
                                        >
                                            {t('licence.offers.title')}
                                        </Link>
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 rounded-reos-md border border-border p-4">
                                <p className="flex items-center gap-2 font-semibold">
                                    <KeyRound aria-hidden className="size-5" />
                                    {t('licence.switch.association')}
                                </p>
                                <Label
                                    htmlFor="association-code"
                                    className="sr-only"
                                >
                                    {t('licence.switch.code')}
                                </Label>
                                <Input
                                    id="association-code"
                                    placeholder={t('licence.switch.code')}
                                    value={code}
                                    maxLength={40}
                                    onChange={(e) => setCode(e.target.value)}
                                />
                                <Button
                                    variant="outline"
                                    disabled={busy || code.trim().length < 4}
                                    onClick={() => become('association')}
                                >
                                    {t('licence.switch.useCode')}
                                </Button>
                            </div>
                            <div className="space-y-2 rounded-reos-md border border-border p-4">
                                <p className="flex items-center gap-2 font-semibold">
                                    <User aria-hidden className="size-5" />
                                    {t('licence.switch.alone')}
                                </p>
                                <Button
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => become('alone')}
                                >
                                    {t('licence.switch.continue')}
                                </Button>
                            </div>
                        </div>
                    </section>
                ) : null}

                {!state.switched &&
                state.check !== null &&
                state.check !== 'verified' ? (
                    <Alert>
                        <AlertTitle>
                            {t(`licence.switch.results.${state.check}`)}
                        </AlertTitle>
                        <AlertDescription className="text-base">
                            {state.check === 'name_mismatch' && state.record
                                ? t('licence.switch.mismatchBody', {
                                      name: state.record.name,
                                  })
                                : state.watching
                                  ? t('licence.switch.watching')
                                  : t('licence.switch.noLookupBody')}
                        </AlertDescription>
                    </Alert>
                ) : null}
            </div>
        </>
    );
}
