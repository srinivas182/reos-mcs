import { Form } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { start, verify } from '@/routes/auth/phone';
import { redirect } from '@/routes/auth/social';

export type SignInOptionsAvailable = {
    google: boolean;
    apple: boolean;
    phone: boolean;
};

/** "or" between the email form and the other ways to sign in. */
export function OrDivider() {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-4 text-sm text-muted-foreground">
            <span className="h-px flex-1 bg-border" />
            {t('signIn.common.or')}
            <span className="h-px flex-1 bg-border" />
        </div>
    );
}

/**
 * Google and Apple side by side (full-page redirects to the provider).
 * Only the options switched on in Settings › Integrations are shown.
 */
export function ProviderButtons({
    available,
}: {
    available: SignInOptionsAvailable;
}) {
    const { t } = useTranslation();
    const providers = (['google', 'apple'] as const).filter(
        (provider) => available[provider],
    );

    if (providers.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {providers.map((provider) => (
                <Button
                    key={provider}
                    variant="outline"
                    asChild
                    className={providers.length === 1 ? 'sm:col-span-2' : ''}
                >
                    <a href={redirect(provider).url}>
                        <ProviderLogo provider={provider} />
                        {t(`signIn.providers.${provider}`)}
                    </a>
                </Button>
            ))}
        </div>
    );
}

/**
 * Phone-code sign-in: number, then the six-digit code. `sentTo` is the number
 * the server just texted, so the code step survives the page reload.
 */
export function PhoneSignIn({
    sentTo,
    initiallyOpen = false,
}: {
    sentTo?: string | null;
    initiallyOpen?: boolean;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(initiallyOpen || Boolean(sentTo));

    if (!open) {
        return (
            <Button
                type="button"
                variant="outline"
                className="w-full"
                onClick={() => setOpen(true)}
            >
                <Smartphone aria-hidden className="size-5" />
                {t('signIn.providers.phone')}
            </Button>
        );
    }

    if (sentTo) {
        return (
            <Form
                {...verify.form()}
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="phone" value={sentTo} />
                        <div className="grid gap-2">
                            <Label htmlFor="code">
                                {t('signIn.phone.codeLabel')}
                            </Label>
                            <p
                                id="code-hint"
                                className="text-sm text-muted-foreground"
                            >
                                {t('signIn.phone.codeHint', { phone: sentTo })}
                            </p>
                            <Input
                                id="code"
                                name="code"
                                inputMode="numeric"
                                autoComplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxLength={6}
                                required
                                autoFocus
                                aria-describedby="code-hint"
                                aria-invalid={errors.code ? true : undefined}
                                className="font-mono text-lg tracking-[0.4em]"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            {t('signIn.phone.verify')}
                        </Button>
                    </>
                )}
            </Form>
        );
    }

    return (
        <Form
            {...start.form()}
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="phone">{t('signIn.phone.label')}</Label>
                        <p
                            id="phone-hint"
                            className="text-sm text-muted-foreground"
                        >
                            {t('signIn.phone.hint')}
                        </p>
                        <Input
                            id="phone"
                            name="phone"
                            type="tel"
                            inputMode="tel"
                            autoComplete="tel"
                            placeholder="+1 512 555 0100"
                            required
                            autoFocus
                            aria-describedby="phone-hint"
                            aria-invalid={errors.phone ? true : undefined}
                        />
                        <InputError message={errors.phone} />
                    </div>
                    <Button
                        type="submit"
                        className="w-full"
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        {t('signIn.phone.send')}
                    </Button>
                </>
            )}
        </Form>
    );
}

/**
 * Small brand marks, drawn inline (no external images). The Google "G" keeps
 * Google's own colours, as its branding guidelines require: the one place
 * raw colours are allowed.
 */
function ProviderLogo({ provider }: { provider: 'google' | 'apple' }) {
    if (provider === 'apple') {
        return (
            <svg
                aria-hidden
                viewBox="0 0 24 24"
                className="size-5 fill-current"
            >
                <path d="M16.37 12.6c-.02-2.1 1.72-3.1 1.8-3.15-.98-1.43-2.5-1.63-3.04-1.65-1.29-.13-2.52.76-3.18.76-.66 0-1.67-.74-2.75-.72-1.41.02-2.72.82-3.44 2.09-1.47 2.55-.38 6.32 1.05 8.39.7 1.01 1.53 2.15 2.62 2.11 1.05-.04 1.45-.68 2.72-.68 1.27 0 1.62.68 2.73.66 1.13-.02 1.85-1.03 2.54-2.05.8-1.17 1.13-2.31 1.15-2.37-.03-.01-2.2-.84-2.2-3.39ZM14.3 6.43c.58-.7.97-1.68.86-2.65-.83.03-1.84.55-2.44 1.25-.53.62-1 1.61-.88 2.56.93.07 1.88-.47 2.46-1.16Z" />
            </svg>
        );
    }

    return (
        <svg aria-hidden viewBox="0 0 24 24" className="size-5">
            <path
                fill="#4285F4"
                d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1Z"
            />
            <path
                fill="#34A853"
                d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0 0 12 23Z"
            />
            <path
                fill="#FBBC05"
                d="M5.84 14.1A6.6 6.6 0 0 1 5.5 12c0-.73.13-1.44.34-2.1V7.06H2.18A11 11 0 0 0 1 12c0 1.78.43 3.45 1.18 4.94l3.66-2.84Z"
            />
            <path
                fill="#EA4335"
                d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15A10.96 10.96 0 0 0 12 1 11 11 0 0 0 2.18 7.06l3.66 2.84C6.71 7.3 9.14 5.38 12 5.38Z"
            />
        </svg>
    );
}
