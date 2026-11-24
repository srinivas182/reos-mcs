import { Copy, Languages, Minimize2, Briefcase, Check } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Generation } from './types';

/**
 * Pick a version (agent screen 166; ad studio 107): designs to choose, three
 * captions with Shorter, More professional and Español, carousel slides and
 * ad copy to copy. Use this puts the design and caption in the composer.
 */
export function Versions({
    generation,
    onRetone,
    onUse,
    busy = false,
}: {
    generation: Generation;
    onRetone: (
        index: number,
        tone: 'shorter' | 'professional' | 'spanish',
    ) => void;
    onUse: (caption: string, media: { path: string; url: string }[]) => void;
    busy?: boolean;
}) {
    const { t } = useTranslation();
    const [design, setDesign] = useState(0);
    const [caption, setCaption] = useState(0);
    const copy = (text: string) => {
        void navigator.clipboard?.writeText(text);
        toast.success(t('studio.maker.copied'));
    };
    const chosen = generation.designs[design];

    return (
        <section
            aria-labelledby="versions-title"
            className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="versions-title" className="text-lg font-semibold">
                    {t('studio.maker.versions')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {t('studio.maker.checked')}
                </p>
            </div>
            {generation.designs.length > 0 ? (
                <fieldset className="space-y-2">
                    <legend className="text-base font-medium">
                        {t('studio.maker.designs')}
                    </legend>
                    <div className="grid grid-cols-3 gap-3">
                        {generation.designs.map((d, i) => (
                            <button
                                key={d.path}
                                type="button"
                                aria-pressed={design === i}
                                aria-label={t('studio.maker.design', {
                                    n: i + 1,
                                })}
                                onClick={() => setDesign(i)}
                                className={cn(
                                    'overflow-hidden rounded-reos-md border-2',
                                    design === i
                                        ? 'border-primary'
                                        : 'border-transparent',
                                )}
                            >
                                <img
                                    src={d.url}
                                    alt=""
                                    className="aspect-square w-full object-cover"
                                />
                            </button>
                        ))}
                    </div>
                </fieldset>
            ) : null}
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('studio.maker.captions')}
                </legend>
                {generation.captions.map((c, i) => (
                    <div
                        key={c.tone}
                        className={cn(
                            'space-y-2 rounded-reos-md border p-4',
                            caption === i ? 'border-primary' : 'border-border',
                        )}
                    >
                        <label className="flex min-h-12 items-start gap-3 text-base">
                            <input
                                type="radio"
                                name="caption"
                                className="mt-1 size-5"
                                checked={caption === i}
                                onChange={() => setCaption(i)}
                            />
                            <span className="space-y-1">
                                <Badge variant="secondary">
                                    {t(`studio.maker.tones.${c.tone}`)}
                                </Badge>
                                <span className="block whitespace-pre-line">
                                    {c.text}
                                </span>
                            </span>
                        </label>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => onRetone(i, 'shorter')}
                            >
                                <Minimize2 aria-hidden />
                                {t('studio.maker.shorter')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => onRetone(i, 'professional')}
                            >
                                <Briefcase aria-hidden />
                                {t('studio.maker.professional')}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={busy}
                                onClick={() => onRetone(i, 'spanish')}
                            >
                                <Languages aria-hidden />
                                {t('studio.maker.spanish')}
                            </Button>
                        </div>
                    </div>
                ))}
            </fieldset>
            {generation.carousel.length > 0 ? (
                <div className="space-y-2">
                    <h3 className="text-base font-semibold">
                        {t('studio.maker.carousel')}
                    </h3>
                    <ul className="flex gap-3 overflow-x-auto pb-2">
                        {generation.carousel.map((s, i) => (
                            <li key={s.path} className="w-32 shrink-0">
                                <img
                                    src={s.url}
                                    alt={t('studio.maker.slide', { n: i + 1 })}
                                    className="aspect-square w-full rounded-reos-md object-cover"
                                />
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            {generation.ad ? (
                <div className="space-y-2">
                    <h3 className="text-base font-semibold">
                        {t('studio.maker.ad')}
                    </h3>
                    {(
                        [
                            ['headlines', generation.ad.headlines],
                            ['primary', generation.ad.primary],
                            ['description', [generation.ad.description]],
                        ] as const
                    ).map(([key, lines]) => (
                        <div key={key} className="space-y-1">
                            <p className="text-sm font-semibold text-muted-foreground">
                                {t(`studio.maker.${key}`)}
                            </p>
                            <ul className="space-y-1">
                                {lines.map((line) => (
                                    <li
                                        key={line}
                                        className="flex items-start justify-between gap-2 text-base"
                                    >
                                        <span>{line}</span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            aria-label={`${t('studio.maker.copy')}: ${line}`}
                                            onClick={() => copy(line)}
                                        >
                                            <Copy aria-hidden />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ))}
                </div>
            ) : null}
            <Button
                type="button"
                disabled={busy}
                onClick={() =>
                    onUse(
                        generation.captions[caption]?.text ?? '',
                        chosen ? [chosen] : generation.carousel,
                    )
                }
            >
                <Check aria-hidden />
                {t('studio.maker.use')}
            </Button>
        </section>
    );
}
