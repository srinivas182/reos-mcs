import { Mail, Share2, Star } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CopyField } from '@/components/copy-field';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { deleteJson, getJson, postJson } from '@/lib/content';

export type PortalLink = {
    id: string;
    name: string;
    email: string | null;
    url: string;
    opened_at: string | null;
    sent_at: string | null;
    expires_at: string;
    review: { rating: number; publish: boolean } | null;
};

/** Where the panel reads and changes a deal's client links. */
export type PortalRoutes = {
    list: string;
    create: string;
    send: (id: string) => string;
    revoke: (id: string) => string;
};

/**
 * Share a deal with a client (B14.2): a private link each, showing the
 * deal's status, key dates and documents to sign, then a review request
 * after closing. No account needed; links can be turned off.
 */
export function DealPortals({
    parties,
    routes,
}: {
    parties: { name: string; email: string | null; role: string }[];
    routes: PortalRoutes;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [links, setLinks] = useState<PortalLink[]>([]);
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [language, setLanguage] = useState<'en' | 'es'>('en');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        void getJson<{ data: PortalLink[] }>(routes.list)
            .then((r) => setLinks(r.data))
            .catch(() => setLinks([]));
    }, [routes.list]);

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        setError(null);
        try {
            await work();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const upsert = (link: PortalLink) =>
        setLinks((now) => [...now.filter((l) => l.id !== link.id), link]);
    const day = new Intl.DateTimeFormat(i18n.language, {
        month: 'short',
        day: 'numeric',
    });
    const clients = parties.filter(
        (p) =>
            p.email !== null &&
            !links.some((l) => l.email === p.email?.toLowerCase()),
    );

    return (
        <section aria-labelledby={`${ids}-title`} className="space-y-3">
            <h3
                id={`${ids}-title`}
                className="flex items-center gap-2 text-base font-semibold"
            >
                <Share2 aria-hidden className="size-5" />
                {t('portal.panel.title')}
            </h3>
            <p className="text-sm text-muted-foreground">
                {t('portal.panel.hint')}
            </p>

            {links.length === 0 ? (
                <EmptyState
                    icon={Share2}
                    title={t('portal.panel.none')}
                    description={t('portal.panel.noneHint')}
                />
            ) : (
                <ul className="space-y-3">
                    {links.map((link) => (
                        <li
                            key={link.id}
                            className="space-y-2 rounded-reos-md border border-border p-3"
                        >
                            <p className="text-base font-medium">
                                {link.name}
                                {link.email ? (
                                    <span className="text-muted-foreground">
                                        {' '}
                                        · {link.email}
                                    </span>
                                ) : null}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {link.opened_at
                                    ? `${t('portal.panel.opened')} ${day.format(new Date(link.opened_at))}`
                                    : t('portal.panel.notOpened')}
                                {link.review ? (
                                    <span className="ml-2 inline-flex items-center gap-1">
                                        <Star aria-hidden className="size-4" />
                                        {t('portal.panel.reviewed', {
                                            rating: link.review.rating,
                                        })}
                                    </span>
                                ) : null}
                            </p>
                            <CopyField
                                label={t('portal.panel.copy')}
                                value={link.url}
                            />
                            <div className="flex flex-wrap gap-2">
                                {link.email ? (
                                    <Button
                                        variant="outline"
                                        disabled={busy}
                                        onClick={() =>
                                            void run(async () => {
                                                upsert(
                                                    (
                                                        await postJson<{
                                                            data: PortalLink;
                                                        }>(routes.send(link.id))
                                                    ).data,
                                                );
                                                toast.success(
                                                    t('portal.panel.sent'),
                                                );
                                            })
                                        }
                                    >
                                        <Mail aria-hidden />
                                        {t('portal.panel.send')}
                                    </Button>
                                ) : null}
                                <Button
                                    variant="ghost"
                                    disabled={busy}
                                    onClick={() =>
                                        void run(async () => {
                                            await deleteJson(
                                                routes.revoke(link.id),
                                            );
                                            setLinks((now) =>
                                                now.filter(
                                                    (l) => l.id !== link.id,
                                                ),
                                            );
                                            toast.success(
                                                t('portal.panel.revoked'),
                                            );
                                        })
                                    }
                                >
                                    {t('portal.panel.revoke')}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {clients.length > 0 ? (
                <div className="flex flex-wrap gap-2">
                    {clients.map((p) => (
                        <Button
                            key={`${p.name}-${p.email}`}
                            variant="outline"
                            onClick={() => {
                                setName(p.name);
                                setEmail(p.email ?? '');
                            }}
                        >
                            {p.name}
                        </Button>
                    ))}
                </div>
            ) : null}

            <form
                className="grid gap-3 sm:grid-cols-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    void run(async () => {
                        upsert(
                            (
                                await postJson<{ data: PortalLink }>(
                                    routes.create,
                                    {
                                        name,
                                        email:
                                            email.trim() === ''
                                                ? null
                                                : email.trim(),
                                        language,
                                    },
                                )
                            ).data,
                        );
                        setName('');
                        setEmail('');
                    });
                }}
            >
                <div className="space-y-2">
                    <Label htmlFor={`${ids}-name`}>
                        {t('portal.panel.client')}
                    </Label>
                    <Input
                        id={`${ids}-name`}
                        required
                        minLength={2}
                        maxLength={120}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor={`${ids}-email`}>
                        {t('portal.panel.email')}
                    </Label>
                    <Input
                        id={`${ids}-email`}
                        type="email"
                        maxLength={160}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                    />
                </div>
                <fieldset className="space-y-2 sm:col-span-2">
                    <legend className="text-sm font-medium">
                        {t('portal.panel.language')}
                    </legend>
                    <div className="flex gap-4">
                        {(['en', 'es'] as const).map((code) => (
                            <label
                                key={code}
                                className="flex min-h-12 items-center gap-2 text-base"
                            >
                                <input
                                    type="radio"
                                    name={`${ids}-language`}
                                    value={code}
                                    checked={language === code}
                                    onChange={() => setLanguage(code)}
                                    className="size-5"
                                />
                                {t(`portal.panel.${code}`)}
                            </label>
                        ))}
                    </div>
                </fieldset>
                <InputError
                    message={error ?? undefined}
                    className="sm:col-span-2"
                />
                <Button
                    type="submit"
                    disabled={busy || name.trim().length < 2}
                    className="sm:col-span-2 sm:justify-self-start"
                >
                    {t('portal.panel.create')}
                </Button>
            </form>
        </section>
    );
}
