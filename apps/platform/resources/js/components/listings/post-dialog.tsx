import { router } from '@inertiajs/react';
import { Check, Copy, Megaphone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { PostDraft } from './types';

/**
 * A listing post (B10.2): pick photos (never more than the MLS allows),
 * write the caption; the MLS's attribution is always added and can't be
 * edited. Copy it to share now, or (B11) save and open it in the content
 * studio to publish or schedule on social accounts.
 */
export function PostDialog({
    draft,
    open,
    onOpenChange,
    onSave,
    publishUrl,
}: {
    draft: PostDraft | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSave: (caption: string, photos: string[]) => Promise<void>;
    /** The content studio, starting from this listing post (B11.1). */
    publishUrl?: (listing: string) => string;
}) {
    const { t } = useTranslation();
    const [caption, setCaption] = useState('');
    const [chosen, setChosen] = useState<string[]>([]);
    const [busy, setBusy] = useState(false);
    const [copied, setCopied] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (draft) {
            setCaption(draft.caption);
            setChosen(draft.chosen);
            setCopied(false);
            setError(null);
        }
    }, [draft]);

    const toggle = (url: string) =>
        setChosen((current) =>
            current.includes(url)
                ? current.filter((u) => u !== url)
                : current.length >= (draft?.max_photos ?? 0)
                  ? current
                  : [...current, url],
        );
    const full = draft ? `${caption.trim()}\n\n${draft.attribution}` : '';
    const save = async () => {
        setBusy(true);
        try {
            await onSave(caption, chosen);
            setError(null);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const copy = async () => {
        await navigator.clipboard?.writeText(full);
        setCopied(true);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{t('listings.post.title')}</DialogTitle>
                    <DialogDescription>
                        {t('listings.post.description')}
                    </DialogDescription>
                </DialogHeader>
                {draft ? (
                    <div className="space-y-5">
                        <fieldset className="space-y-2">
                            <legend className="text-base font-semibold">
                                {t('listings.post.photos')}
                            </legend>
                            <p className="text-sm text-muted-foreground">
                                {t('listings.post.photosHint', {
                                    max: draft.max_photos,
                                    chosen: chosen.length,
                                })}
                            </p>
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {draft.photos.map((photo, i) => {
                                    const on = chosen.includes(photo.url);
                                    const label = t('listings.post.photoAlt', {
                                        n: i + 1,
                                        address: draft.address,
                                    });

                                    return (
                                        <li key={photo.url}>
                                            <button
                                                type="button"
                                                aria-pressed={on}
                                                aria-label={label}
                                                onClick={() =>
                                                    toggle(photo.url)
                                                }
                                                className={`relative block w-full overflow-hidden rounded-reos-md border-2 ${on ? 'border-primary' : 'border-transparent'}`}
                                            >
                                                <img
                                                    src={photo.url}
                                                    alt=""
                                                    className="aspect-[4/3] w-full object-cover"
                                                />
                                                {on ? (
                                                    <span className="absolute top-2 right-2 rounded-reos-pill bg-primary p-1 text-primary-foreground">
                                                        <Check
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                    </span>
                                                ) : null}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </fieldset>
                        <div className="space-y-2">
                            <Label htmlFor="post-caption">
                                {t('listings.post.caption')}
                            </Label>
                            <Textarea
                                id="post-caption"
                                rows={4}
                                maxLength={1800}
                                value={caption}
                                onChange={(e) => setCaption(e.target.value)}
                            />
                        </div>
                        <div className="space-y-1 rounded-reos-md bg-muted p-3">
                            <p className="text-sm font-semibold">
                                {t('listings.post.attributionLabel')}
                            </p>
                            <p className="text-base">{draft.attribution}</p>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('listings.post.channelsNext')}
                        </p>
                        {error ? (
                            <p
                                role="alert"
                                className="text-base text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                ) : null}
                <DialogFooter className="gap-3">
                    <Button
                        variant="outline"
                        disabled={!draft}
                        onClick={() => void copy()}
                    >
                        <Copy aria-hidden />
                        {copied
                            ? t('listings.post.copied')
                            : t('listings.post.copy')}
                    </Button>
                    <Button
                        disabled={!draft || busy || caption.trim() === ''}
                        onClick={() => void save()}
                    >
                        {t('listings.post.save')}
                    </Button>
                    {draft && publishUrl ? (
                        <Button
                            variant="outline"
                            disabled={busy || caption.trim() === ''}
                            onClick={() =>
                                void onSave(caption, chosen).then(() =>
                                    router.visit(publishUrl(draft.listing)),
                                )
                            }
                        >
                            <Megaphone aria-hidden />
                            {t('listings.post.publish')}
                        </Button>
                    ) : null}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
