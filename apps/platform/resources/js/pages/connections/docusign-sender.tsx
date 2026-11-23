import { Head } from '@inertiajs/react';
import { FileText, FlaskConical, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { BrandMark } from '@/components/brand-mark';
import { Button } from '@/components/ui/button';

type Props = {
    subject: string;
    documents: string[];
    signers: { name: string; email: string; routing_order: number }[];
    sendUrl: string;
    cancelUrl: string;
    csrfToken: string;
    account: string | null;
};

/**
 * The demo-data stand-in for DocuSign's sender view (Simulator mode, B9.3).
 * Plainly a REOS demo page, never a copy of DocuSign's: it shows what will
 * be sent and offers Send, which goes back to REOS as DocuSign would.
 */
export default function DocuSignSender({
    subject,
    documents,
    signers,
    sendUrl,
    cancelUrl,
    csrfToken,
    account,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('connections.docusignSim.title')} />
            <main className="flex min-h-dvh items-center justify-center bg-background px-4 py-10">
                <div className="w-full max-w-lg space-y-6 rounded-reos-lg border border-border bg-card p-8">
                    <div className="flex items-center gap-3">
                        <BrandMark className="size-10" />
                        <span className="inline-flex items-center gap-1.5 rounded-reos-pill bg-reos-tone-warning-bg px-3 py-1 text-sm font-semibold text-reos-tone-warning-fg">
                            <FlaskConical aria-hidden className="size-4" />
                            {t('connections.docusignSim.eyebrow')}
                        </span>
                    </div>
                    <header className="space-y-2">
                        <h1 className="text-3xl font-bold tracking-tight text-foreground">
                            {t('connections.docusignSim.title')}
                        </h1>
                        <p className="text-muted-foreground">
                            {t('connections.docusignSim.body')}
                        </p>
                        {account ? (
                            <p className="font-semibold text-foreground">
                                {t('connections.docusignSim.account', {
                                    account,
                                })}
                            </p>
                        ) : null}
                    </header>
                    <p className="text-lg font-semibold text-foreground">
                        {subject}
                    </p>
                    <section className="space-y-2">
                        <h2 className="font-semibold text-foreground">
                            {t('connections.docusignSim.documents')}
                        </h2>
                        <ul className="space-y-1">
                            {documents.map((name) => (
                                <li
                                    key={name}
                                    className="flex items-center gap-2 text-foreground"
                                >
                                    <FileText
                                        aria-hidden
                                        className="size-4 text-primary"
                                    />
                                    {name}
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="space-y-2">
                        <h2 className="font-semibold text-foreground">
                            {t('connections.docusignSim.signers')}
                        </h2>
                        <ul className="space-y-1">
                            {signers.map((s) => (
                                <li
                                    key={`${s.email}-${s.routing_order}`}
                                    className="flex items-center gap-2 text-foreground"
                                >
                                    <User
                                        aria-hidden
                                        className="size-4 text-primary"
                                    />
                                    <span>
                                        {s.name} · {s.email}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {t('connections.docusignSim.order', {
                                            order: s.routing_order,
                                        })}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                    {/* A full-page post: Send redirects back to REOS. */}
                    <form
                        method="post"
                        action={sendUrl}
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        <input type="hidden" name="_token" value={csrfToken} />
                        <Button variant="outline" asChild>
                            <a href={cancelUrl}>
                                {t('connections.docusignSim.cancel')}
                            </a>
                        </Button>
                        <Button type="submit">
                            {t('connections.docusignSim.send')}
                        </Button>
                    </form>
                </div>
            </main>
        </>
    );
}
