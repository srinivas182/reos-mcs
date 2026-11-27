import { ExternalLink } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/copy-field';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { PageSettings } from './types';

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
    onSave: (published: boolean, bio: string) => Promise<void>;
}) {
    const { t } = useTranslation();
    const [published, setPublished] = useState(page.published);
    const [bio, setBio] = useState(page.bio ?? '');
    const [busy, setBusy] = useState(false);
    const [problem, setProblem] = useState<string | null>(null);

    useEffect(() => {
        setPublished(page.published);
        setBio(page.bio ?? '');
    }, [page]);

    const save = async (next: boolean = published) => {
        setBusy(true);
        setProblem(null);
        try {
            await onSave(next, bio);
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
