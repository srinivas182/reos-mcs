import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { LoaderCircle } from 'lucide-react';
import { i18n } from '@/lib/i18n';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';

export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('signIn.forgot.title')} />

            {status && (
                <p
                    role="status"
                    className="mb-4 rounded-reos-md bg-reos-tone-success-bg p-4 text-center font-medium text-reos-tone-success-fg"
                >
                    {status}
                </p>
            )}

            <div className="space-y-6">
                <Form {...email.form()}>
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('signIn.common.email')}
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autoComplete="email"
                                    autoFocus
                                    placeholder={t(
                                        'signIn.common.emailPlaceholder',
                                    )}
                                />

                                <InputError message={errors.email} />
                            </div>

                            <div className="my-6 flex items-center justify-start">
                                <Button
                                    className="w-full"
                                    disabled={processing}
                                    data-test="email-password-reset-link-button"
                                >
                                    {processing && (
                                        <LoaderCircle className="h-4 w-4 animate-spin" />
                                    )}
                                    {t('signIn.forgot.submit')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>

                <p className="text-center text-muted-foreground">
                    {t('signIn.forgot.rememberIt')}{' '}
                    <TextLink href={login()}>
                        {t('signIn.common.backToSignIn')}
                    </TextLink>
                </p>
            </div>
        </>
    );
}

ForgotPassword.layout = {
    get title() {
        return i18n.t('signIn.forgot.title');
    },
    get description() {
        return i18n.t('signIn.forgot.description');
    },
};
