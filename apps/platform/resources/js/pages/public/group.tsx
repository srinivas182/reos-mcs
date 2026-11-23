import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

/** A study group's invite link, signed out (A9.3c): its name and size, and sign in to join. */
export default function PublicGroup({
    name,
    members,
}: {
    name: string;
    members: number;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={name} />
            <div className="mx-auto max-w-2xl space-y-4 px-4 py-10">
                <h1 className="text-3xl font-semibold">{name}</h1>
                <p className="text-body text-muted-foreground">
                    {t('practice.groups.joinBody', { name, count: members })}
                </p>
                <Button asChild>
                    <Link href={login()}>{t('practice.groups.signIn')}</Link>
                </Button>
            </div>
        </>
    );
}
