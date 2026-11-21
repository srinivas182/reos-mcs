import { Form, Head, usePage } from '@inertiajs/react';
import { KeyRound, ShieldCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
/* @chisel-passkeys */
import PasskeyVerify from '@/components/passkey-verify';
/* @end-chisel-passkeys */
import PasswordInput from '@/components/password-input';
import type { SignInOptionsAvailable } from '@/components/sign-in-options';
import {
    OrDivider,
    PhoneSignIn,
    ProviderButtons,
} from '@/components/sign-in-options';
import TextLink from '@/components/text-link';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { i18n } from '@/lib/i18n';
/* @chisel-registration */
import { join, register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
    signInOptions: SignInOptionsAvailable;
    phoneCodeSentTo?: string | null;
    /** The tenant's (or its association's) single sign-on, A4.2c. */
    sso?: { name: string; url: string } | null;
};

/**
 * Sign in (journey screen w002): email and password, Google, Apple, the
 * tenant's single sign-on, phone, passkey.
 */
export default function Login({
    status,
    canResetPassword,
    signInOptions,
    phoneCodeSentTo,
    sso = null,
}: Props) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    return (
        <>
            <Head title={t('signIn.login.title')} />

            <div className="space-y-6">
                {status ? (
                    <Alert>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                ) : null}

                {errors.sign_in ? (
                    <Alert variant="destructive">
                        <AlertDescription>{errors.sign_in}</AlertDescription>
                    </Alert>
                ) : null}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="space-y-5"
                >
                    {({ processing, errors: formErrors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('signIn.common.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus={!phoneCodeSentTo}
                                    autoComplete="email webauthn"
                                    placeholder={t(
                                        'signIn.common.emailPlaceholder',
                                    )}
                                    aria-invalid={
                                        formErrors.email ? true : undefined
                                    }
                                />
                                <InputError message={formErrors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-center">
                                    <Label htmlFor="password">
                                        {t('signIn.common.password')}
                                    </Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="ml-auto text-sm"
                                        >
                                            {t('signIn.login.forgot')}
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    autoComplete="current-password"
                                />
                                <InputError message={formErrors.password} />
                            </div>

                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    defaultChecked
                                />
                                <Label htmlFor="remember">
                                    {t('signIn.login.remember')}
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                {t('signIn.login.submit')}
                            </Button>
                        </>
                    )}
                </Form>

                <OrDivider />

                <div className="space-y-3">
                    <ProviderButtons available={signInOptions} />
                    {sso ? (
                        <Button
                            asChild
                            variant="outline"
                            className="h-auto min-h-12 w-full py-3 whitespace-normal"
                        >
                            {/* A full page visit: it leaves for the identity provider. */}
                            <a href={sso.url}>
                                <KeyRound aria-hidden className="size-5" />
                                {t('signIn.sso', { name: sso.name })}
                            </a>
                        </Button>
                    ) : null}
                    {signInOptions.phone ? (
                        <PhoneSignIn sentTo={phoneCodeSentTo} />
                    ) : null}
                    {/* @chisel-passkeys */}
                    <PasskeyVerify withSeparator={false} />
                    {/* @end-chisel-passkeys */}
                </div>

                <Alert className="border-transparent bg-reos-tone-info-bg text-reos-tone-info-fg">
                    <ShieldCheck aria-hidden />
                    <AlertDescription className="text-reos-tone-info-fg">
                        {t('signIn.login.securityNote')}
                    </AlertDescription>
                </Alert>

                {/* @chisel-registration */}
                <p className="text-center text-muted-foreground">
                    {t('signIn.login.noAccount')}{' '}
                    <TextLink href={register()}>
                        {t('signIn.login.createAccount')}
                    </TextLink>
                </p>
                {/* @end-chisel-registration */}

                <p className="text-center">
                    <TextLink href={join()}>
                        {t('signIn.login.haveCode')}
                    </TextLink>
                </p>
            </div>
        </>
    );
}

Login.layout = {
    get title() {
        return i18n.t('signIn.login.title');
    },
    get description() {
        return i18n.t('signIn.login.description');
    },
};
