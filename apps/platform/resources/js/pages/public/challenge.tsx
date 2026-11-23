import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { login } from '@/routes';

/** A challenge's share link, signed out (A9.3b): who sent it (first name only) and sign in to accept. */
export default function PublicChallenge({
    from,
    questions,
    expired,
}: {
    from: string;
    questions: number;
    expired: boolean;
}) {
    const { t } = useTranslation();
    const title = t('practice.challenges.from', { name: from });

    return (
        <>
            <Head title={title} />
            <div className="mx-auto max-w-2xl space-y-4 px-4 py-10">
                <h1 className="text-3xl font-semibold">{title}</h1>
                <p className="text-body text-muted-foreground">
                    {expired
                        ? t('practice.challenges.ended')
                        : t('practice.challenges.acceptBody', {
                              name: from,
                              count: questions,
                          })}
                </p>
                {expired ? null : (
                    <Button asChild>
                        <Link href={login()}>
                            {t('practice.challenges.signIn')}
                        </Link>
                    </Button>
                )}
            </div>
        </>
    );
}
