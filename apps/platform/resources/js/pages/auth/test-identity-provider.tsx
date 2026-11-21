import { Head } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { i18n } from '@/lib/i18n';

type Person = {
    name: string;
    email: string;
    title: string;
    organisation: string;
    /** Where the signed answer goes, and how, as a real provider sends it. */
    action: string;
    method: 'get' | 'post';
    fields: Record<string, string>;
};

type Props = {
    protocol: 'oidc' | 'saml';
    people: Person[];
};

/**
 * REOS's test identity provider (outside production, A4.2c): stands in for
 * an association's member login so single sign-on can be tried with the
 * journey personas. Each button sends a ready, signed answer with a plain
 * form, so it travels exactly like a real provider's.
 */
export default function TestIdentityProvider({ protocol, people }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('signIn.testIdp.title')} />
            <p className="text-muted-foreground">
                {t('signIn.testIdp.description')}
            </p>
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <FlaskConical aria-hidden className="size-4" />
                {t('signIn.testIdp.protocol', {
                    protocol:
                        protocol === 'saml' ? 'SAML 2.0' : 'OpenID Connect',
                })}
            </p>
            <ul className="space-y-3">
                {people.map((person) => (
                    <li key={person.email}>
                        <form action={person.action} method={person.method}>
                            {Object.entries(person.fields).map(
                                ([name, value]) => (
                                    <input
                                        key={name}
                                        type="hidden"
                                        name={name}
                                        value={value}
                                    />
                                ),
                            )}
                            <Button
                                type="submit"
                                variant="outline"
                                className="h-auto min-h-touch w-full flex-col items-start gap-0.5 py-3 text-left"
                            >
                                <span className="font-semibold">
                                    {person.name}
                                </span>
                                <span className="text-sm font-normal text-muted-foreground">
                                    {person.title} · {person.organisation}
                                </span>
                            </Button>
                        </form>
                    </li>
                ))}
            </ul>
        </>
    );
}

TestIdentityProvider.layout = {
    get title() {
        return i18n.t('signIn.testIdp.title');
    },
};
