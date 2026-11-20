import { Form, Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { i18n } from '@/lib/i18n';

type Persona = {
    name: string;
    email: string;
    title: string;
    organisation: string;
};

type Props = {
    provider: 'google' | 'apple';
    state: string;
    callback: string;
    personas: Persona[];
};

/**
 * Demo sign-in (development and staging only): stands in for Google or
 * Apple while they run on demo data, so every journey persona can be tried.
 */
export default function DemoSignIn({
    provider,
    state,
    callback,
    personas,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('signIn.demo.title')} />
            <p className="text-muted-foreground">
                {t('signIn.demo.description', {
                    provider: provider === 'google' ? 'Google' : 'Apple',
                })}
            </p>
            <ul className="space-y-3">
                {personas.map((persona) => (
                    <li key={persona.email}>
                        <Form action={callback} method="post">
                            <input type="hidden" name="state" value={state} />
                            <input
                                type="hidden"
                                name="demo_email"
                                value={persona.email}
                            />
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-auto min-h-touch w-full flex-col items-start gap-0.5 py-3 text-left"
                            >
                                <span className="font-semibold">
                                    {persona.name}
                                </span>
                                <span className="text-sm font-normal text-muted-foreground">
                                    {persona.title} · {persona.organisation}
                                </span>
                            </Button>
                        </Form>
                    </li>
                ))}
            </ul>
        </>
    );
}

DemoSignIn.layout = {
    get title() {
        return i18n.t('signIn.demo.title');
    },
};
