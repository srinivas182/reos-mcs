import { CheckCircle2, ImagePlus, Trash2, Wand2, XCircle } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    Channel,
    CheckResult,
    Draft,
    ListingDraft,
    Pick,
    SocialAccount,
} from './types';

/**
 * Publish or schedule a post (B11; agent screen 108, in the content
 * studio): text, photos or a video (or the listing's photos), the accounts
 * with switches, the preview with the broker name, the checks, and Now,
 * a time, or a draft. A post that fails a check can't be scheduled.
 */
export function Composer({
    accounts,
    draft,
    listing,
    check,
    onCheck,
    onUpload,
    onSubmit,
    onFix,
}: {
    accounts: SocialAccount[];
    draft: Draft;
    listing: ListingDraft | null;
    check: CheckResult | null;
    onCheck: (draft: Draft) => void;
    onUpload: (
        file: File,
    ) => Promise<{ value: string; preview: string; kind: string }>;
    onSubmit: (draft: Draft) => void;
    /** Fix automatically (screen 167): the words with flagged phrases swapped. */
    onFix?: (body: string) => Promise<string>;
}) {
    const { t } = useTranslation();
    const [value, setValue] = useState(draft);
    const file = useRef<HTMLInputElement>(null);
    const targets = accounts.flatMap((a) =>
        a.connection
            ? a.targets.map((target) => ({
                  connection: a.connection?.id ?? 0,
                  target,
              }))
            : [],
    );
    const picked = (p: Pick) =>
        value.picks.some(
            (q) =>
                q.connection === p.connection &&
                q.channel === p.channel &&
                q.id === p.id,
        );

    useEffect(() => {
        const timer = window.setTimeout(() => onCheck(value), 400);
        return () => window.clearTimeout(timer);
    }, [value, onCheck]);

    const set = (patch: Partial<Draft>) =>
        setValue((v) => ({ ...v, ...patch }));
    const label =
        value.when === 'now'
            ? t('social.compose.publish')
            : value.when === 'later'
              ? t('social.compose.schedule')
              : t('social.compose.saveDraft');

    return (
        <form
            aria-labelledby="composer"
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit(value);
            }}
        >
            <h2 id="composer" className="text-lg font-semibold">
                {t('social.compose.title')}
            </h2>
            {listing ? (
                <p className="text-base text-muted-foreground">
                    {t('social.studio.fromListing', {
                        address: listing.address,
                    })}
                </p>
            ) : null}
            <div className="space-y-2">
                <Label htmlFor="post-body" className="text-base">
                    {t('social.compose.text')}
                </Label>
                <Textarea
                    id="post-body"
                    rows={6}
                    value={value.body}
                    onChange={(e) => set({ body: e.target.value })}
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="post-link" className="text-base">
                    {t('social.compose.link')}
                </Label>
                <Input
                    id="post-link"
                    type="url"
                    inputMode="url"
                    value={value.link}
                    onChange={(e) => set({ link: e.target.value })}
                />
            </div>
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('social.compose.media')}
                </legend>
                {listing ? (
                    <div className="flex flex-wrap gap-3">
                        {listing.photos.map((photo) => {
                            const on = value.media.some(
                                (m) => m.value === photo.url,
                            );
                            return (
                                <label
                                    key={photo.url}
                                    className={cn(
                                        'relative cursor-pointer rounded-reos-md border-2',
                                        on
                                            ? 'border-primary'
                                            : 'border-transparent',
                                    )}
                                >
                                    <img
                                        src={photo.url}
                                        alt={photo.caption ?? listing.address}
                                        className="size-24 rounded-reos-md object-cover"
                                    />
                                    <input
                                        type="checkbox"
                                        className="absolute top-1 left-1 size-5"
                                        checked={on}
                                        aria-label={
                                            photo.caption ?? listing.address
                                        }
                                        onChange={() =>
                                            set({
                                                media: on
                                                    ? value.media.filter(
                                                          (m) =>
                                                              m.value !==
                                                              photo.url,
                                                      )
                                                    : [
                                                          ...value.media,
                                                          {
                                                              value: photo.url,
                                                              preview:
                                                                  photo.url,
                                                              kind: 'image',
                                                          },
                                                      ],
                                            })
                                        }
                                    />
                                </label>
                            );
                        })}
                    </div>
                ) : (
                    <>
                        <ul className="flex flex-wrap gap-3">
                            {value.media.map((m) => (
                                <li key={m.value} className="space-y-1">
                                    {m.kind === 'video' ? (
                                        <video
                                            src={m.preview}
                                            className="size-24 rounded-reos-md object-cover"
                                            muted
                                        />
                                    ) : (
                                        <img
                                            src={m.preview}
                                            alt=""
                                            className="size-24 rounded-reos-md object-cover"
                                        />
                                    )}
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() =>
                                            set({
                                                media: value.media.filter(
                                                    (x) => x.value !== m.value,
                                                ),
                                            })
                                        }
                                    >
                                        <Trash2 aria-hidden />
                                        {t('social.compose.removeMedia')}
                                    </Button>
                                </li>
                            ))}
                        </ul>
                        <input
                            ref={file}
                            type="file"
                            accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime"
                            className="sr-only"
                            aria-label={t('social.compose.addMedia')}
                            onChange={(e) => {
                                const chosen = e.target.files?.[0];
                                if (chosen)
                                    void onUpload(chosen).then((m) =>
                                        set({ media: [...value.media, m] }),
                                    );
                                e.target.value = '';
                            }}
                        />
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => file.current?.click()}
                        >
                            <ImagePlus aria-hidden />
                            {t('social.compose.addMedia')}
                        </Button>
                    </>
                )}
            </fieldset>
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('social.compose.chooseAccounts')}
                </legend>
                {targets.map(({ connection, target }) => {
                    const pick: Pick = {
                        connection,
                        channel: target.channel,
                        id: target.id,
                    };
                    const id = `target-${connection}-${target.channel}-${target.id}`;
                    return (
                        <div
                            key={id}
                            className="flex min-h-12 items-center justify-between gap-3"
                        >
                            <Label
                                htmlFor={id}
                                className="text-base font-normal"
                            >
                                <span className="font-semibold">
                                    {t(`social.channels.${target.channel}`)}
                                </span>{' '}
                                · {target.name}
                            </Label>
                            <Switch
                                id={id}
                                checked={picked(pick)}
                                onCheckedChange={(on) =>
                                    set({
                                        picks: on
                                            ? [...value.picks, pick]
                                            : value.picks.filter(
                                                  (q) =>
                                                      !(
                                                          q.connection ===
                                                              connection &&
                                                          q.channel ===
                                                              target.channel &&
                                                          q.id === target.id
                                                      ),
                                              ),
                                    })
                                }
                            />
                        </div>
                    );
                })}
            </fieldset>
            <div className="space-y-2 rounded-reos-md bg-muted p-4">
                <p className="text-base font-semibold">
                    {t('social.compose.preview')}
                </p>
                <p className="text-base whitespace-pre-line">{value.body}</p>
                {check ? (
                    <p className="text-base whitespace-pre-line text-muted-foreground">
                        <span className="sr-only">
                            {t('social.compose.attribution')}:{' '}
                        </span>
                        {check.attribution}
                    </p>
                ) : null}
            </div>
            {check ? (
                <div className="space-y-2">
                    <p className="text-base font-semibold">
                        {t('social.compose.checks')}
                    </p>
                    <ul className="space-y-1">
                        {check.checks.map((c, i) => (
                            <li
                                key={`${c.code}-${c.detail ?? i}`}
                                className="flex items-center gap-2 text-base"
                            >
                                {c.ok ? (
                                    <CheckCircle2
                                        aria-hidden
                                        className="size-5 text-reos-tone-success-fg"
                                    />
                                ) : (
                                    <XCircle
                                        aria-hidden
                                        className="size-5 text-destructive"
                                    />
                                )}
                                {['length', 'media', 'video'].includes(c.code)
                                    ? t(`social.checks.${c.code}`, {
                                          channel: c.detail
                                              ? t(
                                                    `social.channels.${c.detail as Channel}`,
                                                )
                                              : '',
                                      })
                                    : t(`social.checks.${c.code}`, {
                                          channel: '',
                                      })}
                                {!c.ok &&
                                c.detail &&
                                !['length', 'media', 'video'].includes(c.code)
                                    ? `: ${c.detail}`
                                    : ''}
                                {!c.ok && c.citation ? (
                                    <span className="text-sm text-muted-foreground">
                                        {` (${c.citation})`}
                                    </span>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    {check.issues.length > 0 && onFix ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                void onFix(value.body).then((body) =>
                                    set({ body }),
                                )
                            }
                        >
                            <Wand2 aria-hidden />
                            {t('studio.checks.fix')}
                        </Button>
                    ) : null}
                    {check.review.length > 0 ? (
                        <p className="rounded-reos-md border border-reos-tone-warning-fg/30 bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg">
                            {t('compliance.review', {
                                phrases: check.review.join(', '),
                            })}
                        </p>
                    ) : null}
                    {check.approval ? (
                        <p className="rounded-reos-md border border-reos-tone-info-fg/30 bg-reos-tone-info-bg p-3 text-base text-reos-tone-info-fg">
                            {t('studio.checks.approval')}
                        </p>
                    ) : null}
                </div>
            ) : null}
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('social.compose.when')}
                </legend>
                {(['now', 'later', 'draft'] as const).map((when) => (
                    <label
                        key={when}
                        className="flex min-h-12 items-center gap-3 text-base"
                    >
                        <input
                            type="radio"
                            name="when"
                            className="size-5"
                            checked={value.when === when}
                            onChange={() => set({ when })}
                        />
                        {t(`social.compose.${when}`)}
                    </label>
                ))}
                {value.when === 'later' ? (
                    <div className="space-y-2">
                        <Label htmlFor="post-at" className="text-base">
                            {t('social.compose.at')}
                        </Label>
                        <Input
                            id="post-at"
                            type="datetime-local"
                            className="w-64"
                            value={value.at}
                            onChange={(e) => set({ at: e.target.value })}
                        />
                    </div>
                ) : null}
            </fieldset>
            {check && !check.ok && value.when !== 'draft' ? (
                <p className="text-base text-destructive">
                    {t('social.compose.fixFirst')}
                </p>
            ) : null}
            <Button
                type="submit"
                disabled={value.when !== 'draft' && check?.ok !== true}
            >
                {label}
            </Button>
        </form>
    );
}
