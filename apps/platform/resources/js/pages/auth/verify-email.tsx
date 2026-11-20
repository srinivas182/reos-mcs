import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { i18n } from '@/lib/i18n';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('signIn.verify.title')} />

            {status === 'verification-link-sent' && (
                <p
                    role="status"
                    className="rounded-reos-md bg-reos-tone-success-bg p-4 text-center font-medium text-reos-tone-success-fg"
                >
                    {t('signIn.verify.sent')}
                </p>
            )}

            <Form {...send.form()} className="space-y-6 text-center">
                {({ processing }) => (
                    <>
                        <Button disabled={processing} variant="secondary">
                            {processing && <Spinner />}
                            {t('signIn.verify.resend')}
                        </Button>

                        <TextLink
                            href={logout()}
                            className="mx-auto block text-sm"
                        >
                            {t('signIn.common.signOut')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}

VerifyEmail.layout = {
    get title() {
        return i18n.t('signIn.verify.title');
    },
    get description() {
        return i18n.t('signIn.verify.description');
    },
};
