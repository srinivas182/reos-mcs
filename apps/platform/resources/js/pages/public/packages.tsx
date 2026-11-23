import { Head, Link, router } from '@inertiajs/react';
import { Check, Ticket } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money, priceLabel } from '@/lib/billing';
import type { Interval } from '@/lib/billing';
import { postJson } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { index as billing } from '@/routes/billing';
import { show as checkout } from '@/routes/checkout';
import { packages as packagesRoute } from '@/routes';
import { code as checkCode } from '@/routes/packages';

type Package = {
    id: number;
    product: string;
    kind: string;
    description: string | null;
    includes: string[];
    interval: Interval;
    amount: number;
    charge: number | null;
    sponsor: string | null;
    discounts: { sponsor: string; amount: number }[];
};

type CodePreview = {
    code: string;
    sponsor: string;
    product: string;
    state: string;
    months: number | null;
    expires_at: string | null;
};

/**
 * Packages and prices for candidates on reos.com (A8.3b; the Membership
 * screen's link from the iPhone app): each plan for the state with what's
 * included, what sponsoring schools take off, and, signed in, the person's
 * own price; Buy goes to checkout (B5.1). "Have a code?" shows what a
 * school's, brokerage's or association's code covers before it's used. The
 * phone app never shows a price.
 */
export default function PackagesPage({
    state,
    states,
    packages,
    signedIn,
}: {
    state: { code: string; name: string };
    states: { code: string; name: string }[];
    packages: Package[];
    signedIn: boolean;
}) {
    const { t, i18n } = useTranslation();
    const [code, setCode] = useState('');
    const [preview, setPreview] = useState<CodePreview | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [checking, setChecking] = useState(false);

    const check = async (event: FormEvent) => {
        event.preventDefault();
        setChecking(true);
        setError(null);
        setPreview(null);
        try {
            setPreview(
                (
                    await postJson<{ data: CodePreview }>(checkCode().url, {
                        code,
                    })
                ).data,
            );
        } catch (e) {
            setError(
                e instanceof Error ? e.message : t('site.packages.codeFailed'),
            );
        } finally {
            setChecking(false);
        }
    };

    return (
        <>
            <Head title={t('site.packages.title', { state: state.name })}>
                <meta
                    name="description"
                    content={t('site.packages.intro', { state: state.name })}
                />
            </Head>
            <div className="mx-auto max-w-5xl space-y-10 px-4 py-12 md:px-8 lg:py-16">
                <header className="flex flex-wrap items-end justify-between gap-6">
                    <div className="max-w-2xl space-y-3">
                        <p className="text-sm font-semibold tracking-wide text-primary uppercase">
                            {t('site.packages.eyebrow')}
                        </p>
                        <h1 className="text-4xl leading-tight font-bold tracking-tight text-balance text-foreground">
                            {t('site.packages.title', { state: state.name })}
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            {t('site.packages.intro', { state: state.name })}
                        </p>
                    </div>
                    {states.length > 1 ? (
                        <label className="grid gap-2 text-body">
                            {t('site.packages.state')}
                            <select
                                className={`${nativeSelect} w-56`}
                                value={state.code}
                                onChange={(e) =>
                                    router.visit(
                                        packagesRoute(e.target.value).url,
                                    )
                                }
                            >
                                {states.map((s) => (
                                    <option key={s.code} value={s.code}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    ) : null}
                </header>

                {packages.length === 0 ? (
                    <p className="rounded-reos-lg border border-border bg-card p-6 text-body text-muted-foreground">
                        {t('site.packages.none', { state: state.name })}
                    </p>
                ) : (
                    <ul className="grid gap-6 md:grid-cols-3">
                        {packages.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-col gap-4 rounded-reos-lg border border-border bg-card p-6"
                            >
                                <div className="space-y-2">
                                    <h2 className="text-xl font-semibold">
                                        {p.product}
                                    </h2>
                                    {p.description ? (
                                        <p className="text-body text-muted-foreground">
                                            {p.description}
                                        </p>
                                    ) : null}
                                </div>
                                <p className="text-3xl font-bold tabular-nums">
                                    {priceLabel(
                                        t,
                                        p.interval,
                                        p.charge ?? p.amount,
                                        i18n.language,
                                    )}
                                </p>
                                {p.charge !== null && p.charge < p.amount ? (
                                    <p className="text-body text-reos-tone-success-fg">
                                        {t('site.packages.yourPrice', {
                                            sponsor: p.sponsor,
                                            amount: money(
                                                p.amount,
                                                i18n.language,
                                            ),
                                        })}
                                    </p>
                                ) : null}
                                <ul className="flex-1 space-y-2">
                                    {p.includes.map((item) => (
                                        <li
                                            key={item}
                                            className="flex gap-2 text-body"
                                        >
                                            <Check
                                                aria-hidden
                                                className="mt-0.5 size-5 shrink-0 text-primary"
                                            />
                                            {item}
                                        </li>
                                    ))}
                                </ul>
                                {p.discounts.length > 0 && p.charge === null ? (
                                    <ul className="space-y-1 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                                        {p.discounts.map((d) => (
                                            <li key={d.sponsor}>
                                                {t('site.packages.discount', {
                                                    sponsor: d.sponsor,
                                                    amount: money(
                                                        d.amount,
                                                        i18n.language,
                                                    ),
                                                })}
                                            </li>
                                        ))}
                                    </ul>
                                ) : null}
                                <Button asChild>
                                    <Link
                                        href={checkout(p.id)}
                                        aria-label={t(
                                            'site.packages.buyNamed',
                                            {
                                                product: p.product,
                                            },
                                        )}
                                    >
                                        {t('site.packages.buy')}
                                    </Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <section className="grid gap-6 rounded-reos-lg border border-border bg-card p-6 md:grid-cols-[1fr_1fr]">
                    <div className="space-y-2">
                        <h2 className="flex items-center gap-2 text-xl font-semibold">
                            <Ticket
                                aria-hidden
                                className="size-5 text-primary"
                            />
                            {t('site.packages.codeTitle')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('site.packages.codeIntro')}
                        </p>
                    </div>
                    <div className="space-y-3">
                        <form
                            onSubmit={(e) => void check(e)}
                            className="flex flex-wrap items-end gap-3"
                        >
                            <div className="grid min-w-48 flex-1 gap-2">
                                <Label htmlFor="code">
                                    {t('billing.codes.label')}
                                </Label>
                                <Input
                                    id="code"
                                    value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                    autoComplete="off"
                                    maxLength={40}
                                    className="font-mono uppercase"
                                    aria-describedby={
                                        error ? 'code-error' : undefined
                                    }
                                />
                            </div>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={checking || code.trim() === ''}
                            >
                                {t('site.packages.check')}
                            </Button>
                        </form>
                        {error ? (
                            <p
                                id="code-error"
                                role="alert"
                                className="text-body text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                        {preview ? (
                            <div
                                role="status"
                                className="space-y-3 rounded-reos-md bg-reos-tone-success-bg p-4 text-body text-reos-tone-success-fg"
                            >
                                <p className="font-semibold">
                                    {t('site.packages.covers', {
                                        sponsor: preview.sponsor,
                                        product: preview.product,
                                    })}
                                </p>
                                <p>
                                    {preview.months
                                        ? t('site.packages.months', {
                                              count: preview.months,
                                          })
                                        : t('site.packages.asLong')}{' '}
                                    {t('site.packages.noObligation')}
                                </p>
                                <Button asChild>
                                    <Link
                                        href={billing({
                                            query: { code: preview.code },
                                        })}
                                    >
                                        {signedIn
                                            ? t('site.packages.useCode')
                                            : t('site.packages.signInToUse')}
                                    </Link>
                                </Button>
                            </div>
                        ) : null}
                    </div>
                </section>
            </div>
        </>
    );
}
