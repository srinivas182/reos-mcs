import { Download, ExternalLink, IdCard } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/copy-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { CardDetails, PageSettings } from './types';

/** TREC's forms every Texas agent links from their website and social profiles (22 TAC §531.18, §531.20). */
const TREC_IABS =
    'https://www.trec.texas.gov/forms/information-about-brokerage-services';
const TREC_CPN = 'https://www.trec.texas.gov/forms/consumer-protection-notice';

/**
 * The agent's public page (B10.2b): publish it, write a short bio, copy the
 * link. The listings on it follow each MLS's display rules.
 */
export function PageCard({
    page,
    onSave,
}: {
    page: PageSettings;
    onSave: (
        published: boolean,
        bio: string,
        card: CardDetails,
    ) => Promise<void>;
}) {
    const { t } = useTranslation();
    const [published, setPublished] = useState(page.published);
    const [bio, setBio] = useState(page.bio ?? '');
    const [phone, setPhone] = useState(page.phone ?? '');
    const [email, setEmail] = useState(page.email ?? '');
    const [areas, setAreas] = useState(page.areas.join(', '));
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);

    useEffect(() => {
        setPublished(page.published);
        setBio(page.bio ?? '');
        setPhone(page.phone ?? '');
        setEmail(page.email ?? '');
        setAreas(page.areas.join(', '));
    }, [page]);

    const save = async (next: boolean = published) => {
        setBusy(true);
        setProblem(null);
        try {
            await onSave(next, bio, {
                phone: phone.trim() === '' ? null : phone.trim(),
                email: email.trim() === '' ? null : email.trim(),
                areas: areas
                    .split(',')
                    .map((a) => a.trim())
                    .filter((a) => a !== '')
                    .slice(0, 6),
            });
        } catch (e) {
            // The compliance engine (B13.1): what keeps the page from being published.
            setPublished(page.published);
            setProblem(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <section
            aria-labelledby="public-page"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="public-page" className="text-lg font-semibold">
                    {t('listings.page.title')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('listings.page.description')}
                </p>
            </div>
            <div className="flex flex-wrap items-center gap-3">
                <Switch
                    id="page-published"
                    checked={published}
                    disabled={busy}
                    onCheckedChange={(on) => {
                        setPublished(on);
                        void save(on);
                    }}
                />
                <Label htmlFor="page-published" className="text-base">
                    {t('listings.page.publish')}
                </Label>
                <span className="text-sm text-muted-foreground">
                    {published
                        ? t('listings.page.published')
                        : t('listings.page.hidden')}
                </span>
            </div>
            <div className="space-y-2">
                <Label htmlFor="page-bio">{t('listings.page.bio')}</Label>
                <Textarea
                    id="page-bio"
                    rows={3}
                    maxLength={600}
                    value={bio}
                    onChange={(e) => setBio(e.target.value)}
                    aria-describedby="page-bio-hint"
                />
                <p id="page-bio-hint" className="text-sm text-muted-foreground">
                    {t('listings.page.bioHint')}
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="page-phone">
                        {t('listings.page.phone')}
                    </Label>
                    <Input
                        id="page-phone"
                        type="tel"
                        autoComplete="tel"
                        maxLength={30}
                        value={phone}
                        onChange={(e) => setPhone(e.target.value)}
                        aria-describedby="page-contact-hint"
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="page-email">
                        {t('listings.page.email')}
                    </Label>
                    <Input
                        id="page-email"
                        type="email"
                        autoComplete="email"
                        maxLength={120}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        aria-describedby="page-contact-hint"
                    />
                </div>
                <p
                    id="page-contact-hint"
                    className="text-sm text-muted-foreground sm:col-span-2"
                >
                    {t('listings.page.contactHint')}
                </p>
                <div className="space-y-2 sm:col-span-2">
                    <Label htmlFor="page-areas">
                        {t('listings.page.areas')}
                    </Label>
                    <Input
                        id="page-areas"
                        maxLength={400}
                        value={areas}
                        onChange={(e) => setAreas(e.target.value)}
                        aria-describedby="page-areas-hint"
                    />
                    <p
                        id="page-areas-hint"
                        className="text-sm text-muted-foreground"
                    >
                        {t('listings.page.areasHint')}
                    </p>
                </div>
            </div>
            {page.waiting ? (
                <p className="rounded-reos-md border border-reos-tone-info-fg/30 bg-reos-tone-info-bg p-3 text-base text-reos-tone-info-fg">
                    {t('compliance.pageWaiting')}
                </p>
            ) : null}
            {page.note ? (
                <p className="rounded-reos-md border border-reos-tone-warning-fg/30 bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg">
                    {t('compliance.pageReturned', { note: page.note })}
                </p>
            ) : null}
            {problem ? (
                <p
                    role="alert"
                    className="rounded-reos-md border border-destructive/30 bg-reos-tone-danger-bg p-3 text-base text-reos-tone-danger-fg"
                >
                    {problem}
                </p>
            ) : null}
            {published ? (
                <CopyField label={t('listings.page.link')} value={page.url} />
            ) : null}
            {published ? (
                <div className="space-y-3 rounded-reos-md border border-border p-4">
                    <h3 className="flex items-center gap-2 text-base font-semibold">
                        <IdCard aria-hidden className="size-5" />
                        {t('listings.page.cardTitle')}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {t('listings.page.cardHint')}
                    </p>
                    <div className="flex flex-wrap items-center gap-4">
                        <img
                            src={page.qr_url}
                            alt={t('listings.page.qrAlt')}
                            width={128}
                            height={128}
                            className="rounded-reos-md border border-border bg-card p-1"
                        />
                        <div className="flex flex-col gap-2">
                            <Button asChild variant="outline">
                                <a
                                    href={page.card_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <IdCard aria-hidden />
                                    {t('listings.page.openCard')}
                                </a>
                            </Button>
                            <Button asChild variant="outline">
                                <a href={page.vcard_url}>
                                    <Download aria-hidden />
                                    {t('listings.page.downloadCard')}
                                </a>
                            </Button>
                        </div>
                    </div>
                    <CopyField
                        label={t('listings.page.cardTitle')}
                        value={page.card_url}
                    />
                </div>
            ) : null}
            <div className="space-y-2">
                <h3 className="text-base font-semibold">
                    {t('compliance.profileLinks')}
                </h3>
                <p className="text-sm text-muted-foreground">
                    {t('compliance.profileLinksHint')}
                </p>
                <CopyField label={t('compliance.iabs')} value={TREC_IABS} />
                <CopyField label={t('compliance.cpn')} value={TREC_CPN} />
            </div>
            <div className="flex flex-wrap gap-3">
                <Button disabled={busy} onClick={() => void save()}>
                    {t('listings.page.save')}
                </Button>
                {published ? (
                    <Button asChild variant="outline">
                        <a href={page.url} target="_blank" rel="noreferrer">
                            <ExternalLink aria-hidden />
                            {t('listings.page.view')}
                        </a>
                    </Button>
                ) : null}
            </div>
        </section>
    );
}
