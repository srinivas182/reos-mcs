import { Copy, Download, PartyPopper, Send, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type { MadePost, Moment, Target } from './types';

/** The candidate's moments, each ready to become a free post. */
export function MomentList({
    moments,
    busy = false,
    onOpen,
}: {
    moments: Moment[];
    busy?: boolean;
    onOpen: (m: Moment) => void;
}) {
    const { t } = useTranslation();

    if (moments.length === 0) {
        return (
            <EmptyState
                icon={PartyPopper}
                title={t('studio.candidate.none')}
                description={t('studio.candidate.noneHint')}
            />
        );
    }

    return (
        <ul className="grid gap-3 md:grid-cols-3">
            {moments.map((m) => (
                <li
                    key={m.milestone}
                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                >
                    <p className="text-sm font-semibold text-muted-foreground uppercase">
                        {t(`studio.celebrate.names.${m.milestone}`)}
                    </p>
                    <p className="text-lg font-semibold">{m.label}</p>
                    <Button disabled={busy} onClick={() => onOpen(m)}>
                        {m.made
                            ? t('studio.candidate.open')
                            : t('studio.candidate.make')}
                    </Button>
                </li>
            ))}
        </ul>
    );
}

/**
 * One post: three captions and three designs, the caption to edit with
 * Check, then Post now (connected accounts) or share it yourself.
 */
export function MadePostEditor({
    post,
    targets,
    busy = false,
    onCheck,
    onPost,
    onCopy,
}: {
    post: MadePost;
    targets: Target[];
    busy?: boolean;
    onCheck: (text: string) => Promise<{
        ok: boolean;
        issues: { phrase: string | null; citation: string }[];
    }>;
    onPost: (
        caption: number,
        design: number,
        text: string,
        targets: Target[],
    ) => void;
    onCopy: (text: string) => void;
}) {
    const { t } = useTranslation();
    const [caption, setCaption] = useState(0);
    const [design, setDesign] = useState(0);
    const [text, setText] = useState(post.captions[0]?.text ?? '');
    const [result, setResult] = useState<{
        ok: boolean;
        issues: { phrase: string | null; citation: string }[];
    } | null>(null);
    const image = post.designs[design];

    return (
        <section
            aria-labelledby="made-title"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="made-title" className="text-lg font-semibold">
                {post.label}
            </h2>
            <p className="text-sm text-muted-foreground">
                {t('studio.candidate.rule')}
            </p>
            <div className="space-y-2">
                <p className="text-base font-semibold">
                    {t('studio.candidate.designs')}
                </p>
                <div className="flex flex-wrap gap-3">
                    {post.designs.map((d, i) => (
                        <button
                            key={d.path}
                            type="button"
                            aria-label={t('studio.candidate.design', {
                                n: i + 1,
                            })}
                            aria-pressed={i === design}
                            onClick={() => setDesign(i)}
                            className={cn(
                                'size-28 overflow-hidden rounded-reos-md border-2',
                                i === design
                                    ? 'border-reos-action'
                                    : 'border-border',
                            )}
                        >
                            {d.url ? (
                                <img
                                    src={d.url}
                                    alt=""
                                    className="size-full object-cover"
                                />
                            ) : null}
                        </button>
                    ))}
                </div>
            </div>
            <fieldset className="space-y-2">
                <legend className="text-base font-semibold">
                    {t('studio.candidate.captions')}
                </legend>
                {post.captions.map((c, i) => (
                    <label
                        key={c.tone}
                        className="flex items-start gap-3 rounded-reos-md border border-border p-3 text-base"
                    >
                        <input
                            type="radio"
                            name="caption"
                            className="mt-1 size-5"
                            checked={caption === i}
                            onChange={() => {
                                setCaption(i);
                                setText(c.text);
                                setResult(null);
                            }}
                        />
                        <span>{c.text}</span>
                    </label>
                ))}
            </fieldset>
            <div className="space-y-2">
                <Label htmlFor="candidate-caption">
                    {t('studio.candidate.edit')}
                </Label>
                <Textarea
                    id="candidate-caption"
                    value={text}
                    maxLength={2000}
                    onChange={(e) => {
                        setText(e.target.value);
                        setResult(null);
                    }}
                />
            </div>
            <div aria-live="polite">
                {result?.ok ? (
                    <p className="text-base text-reos-tone-success-fg">
                        {t('studio.candidate.ok')}
                    </p>
                ) : null}
                {result && !result.ok ? (
                    <ul className="space-y-1 rounded-reos-md bg-reos-tone-danger-bg p-3 text-base text-reos-tone-danger-fg">
                        {result.issues.map((i, n) => (
                            <li key={`${i.phrase ?? ''}-${n}`}>
                                {t('compliance.blocked.unlicensed', {
                                    phrase: i.phrase ?? '',
                                    citation: i.citation,
                                })}
                            </li>
                        ))}
                    </ul>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    disabled={busy}
                    onClick={() => void onCheck(text).then(setResult)}
                >
                    <ShieldCheck aria-hidden />
                    {t('studio.candidate.check')}
                </Button>
                {targets.length > 0 ? (
                    <Button
                        disabled={busy || result?.ok === false}
                        onClick={() => onPost(caption, design, text, targets)}
                    >
                        <Send aria-hidden />
                        {t('studio.candidate.post')}
                    </Button>
                ) : null}
                {image?.url ? (
                    <Button variant="outline" asChild>
                        <a href={image.url} download>
                            <Download aria-hidden />
                            {t('studio.candidate.download')}
                        </a>
                    </Button>
                ) : null}
                <Button
                    variant="outline"
                    disabled={result?.ok === false}
                    onClick={() => onCopy(text)}
                >
                    <Copy aria-hidden />
                    {t('studio.candidate.copy')}
                </Button>
            </div>
            {targets.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('studio.candidate.noAccounts')}
                </p>
            ) : null}
        </section>
    );
}
