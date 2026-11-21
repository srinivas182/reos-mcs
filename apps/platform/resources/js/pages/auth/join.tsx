import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { i18n } from '@/lib/i18n';
import { find } from '@/routes/join';

/** "Have an invite code?" (A4.3a): the 8-character code from a text or email. */
export default function Join() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('joinPage.title')} />
            <Form {...find.form()} className="space-y-5">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="join-code">
                                {t('joinPage.code')}
                            </Label>
                            <Input
                                id="join-code"
                                name="code"
                                required
                                autoFocus
                                autoComplete="one-time-code"
                                autoCapitalize="characters"
                                spellCheck={false}
                                placeholder={t('joinPage.placeholder')}
                                className="font-mono tracking-widest uppercase"
                                aria-invalid={Boolean(errors.code)}
                            />
                            <InputError message={errors.code} />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {t('joinPage.continue')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

Join.layout = {
    get title() {
        return i18n.t('joinPage.title');
    },
    get description() {
        return i18n.t('joinPage.description');
    },
};
