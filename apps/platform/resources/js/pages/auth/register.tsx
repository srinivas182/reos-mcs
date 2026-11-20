import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import type { SignInOptionsAvailable } from '@/components/sign-in-options';
import {
    OrDivider,
    PhoneSignIn,
    ProviderButtons,
} from '@/components/sign-in-options';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { i18n } from '@/lib/i18n';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
    signInOptions: SignInOptionsAvailable;
};

/** Create an account: Google, Apple or phone in one tap, or email and password. */
export default function Register({ passwordRules, signInOptions }: Props) {
    const { t } = useTranslation();
    const quick =
        signInOptions.google || signInOptions.apple || signInOptions.phone;

    return (
        <>
            <Head title={t('signIn.register.title')} />

            <div className="space-y-6">
                {quick ? (
                    <>
                        <div className="space-y-3">
                            <ProviderButtons available={signInOptions} />
                            {signInOptions.phone ? <PhoneSignIn /> : null}
                        </div>
                        <OrDivider />
                    </>
                ) : null}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password', 'password_confirmation']}
                    disableWhileProcessing
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('signIn.common.name')}
                                </Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoComplete="name"
                                    name="name"
                                    aria-invalid={
                                        errors.name ? true : undefined
                                    }
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('signIn.common.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    autoComplete="email"
                                    name="email"
                                    placeholder={t(
                                        'signIn.common.emailPlaceholder',
                                    )}
                                    aria-invalid={
                                        errors.email ? true : undefined
                                    }
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('signIn.common.password')}
                                </Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    autoComplete="new-password"
                                    name="password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('signIn.common.confirmPassword')}
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                {t('signIn.register.submit')}
                            </Button>
                        </>
                    )}
                </Form>

                <p className="text-center text-muted-foreground">
                    {t('signIn.register.haveAccount')}{' '}
                    <TextLink href={login()}>
                        {t('signIn.register.signIn')}
                    </TextLink>
                </p>
            </div>
        </>
    );
}

Register.layout = {
    get title() {
        return i18n.t('signIn.register.title');
    },
    get description() {
        return i18n.t('signIn.register.description');
    },
};
