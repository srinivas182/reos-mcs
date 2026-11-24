import { FileText, FileUp, Images, Sparkles } from 'lucide-react';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { ContractsOverview } from './types';

/**
 * Review a contract: choose (agent screen 63; web screen 15): upload a PDF
 * or photos of pages, a deal's document, or the sample TREC 20-18. The
 * cost in credits shows before anything is spent.
 */
export function ChooseContract({
    overview,
    onFile,
    onDealFile,
    onSample,
    busy = false,
}: {
    overview: ContractsOverview;
    onFile: (file: File) => void;
    onDealFile: (uuid: string) => void;
    onSample: () => void;
    busy?: boolean;
}) {
    const { t } = useTranslation();
    const pdf = useRef<HTMLInputElement>(null);
    const photos = useRef<HTMLInputElement>(null);
    const pick = (input: HTMLInputElement | null) => input?.click();
    const chosen = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) onFile(file);
        e.target.value = '';
    };

    return (
        <section
            aria-labelledby="cr-choose"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <h2 id="cr-choose" className="text-lg font-semibold">
                    {t('contracts.web.choose')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('contracts.web.cost', {
                        credits: overview.cost,
                        left: overview.credits_left,
                    })}
                </p>
            </div>
            <input
                ref={pdf}
                type="file"
                accept="application/pdf,.pdf"
                className="sr-only"
                aria-label={t('contracts.web.upload')}
                onChange={chosen}
            />
            <input
                ref={photos}
                type="file"
                accept="image/jpeg,image/png"
                className="sr-only"
                aria-label={t('contracts.web.photos')}
                onChange={chosen}
            />
            <div className="flex flex-wrap gap-3">
                <Button
                    type="button"
                    disabled={busy}
                    onClick={() => pick(pdf.current)}
                >
                    <FileUp aria-hidden />
                    {t('contracts.web.upload')}
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() => pick(photos.current)}
                >
                    <Images aria-hidden />
                    {t('contracts.web.photos')}
                </Button>
                {overview.sample ? (
                    <Button
                        type="button"
                        variant="outline"
                        disabled={busy}
                        onClick={onSample}
                    >
                        <Sparkles aria-hidden />
                        {t('contracts.web.sample')}
                    </Button>
                ) : null}
            </div>
            {overview.files.length > 0 ? (
                <div className="space-y-2">
                    <h3 className="text-base font-semibold">
                        {t('contracts.web.fromDeals')}
                    </h3>
                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                        {overview.files.map((f) => (
                            <li
                                key={f.uuid}
                                className="flex flex-wrap items-center justify-between gap-3 p-3"
                            >
                                <span className="flex min-w-0 items-center gap-2 text-base">
                                    <FileText
                                        aria-hidden
                                        className="size-5 shrink-0 text-muted-foreground"
                                    />
                                    <span className="truncate">{f.name}</span>
                                    <span className="text-muted-foreground">
                                        · {f.deal}
                                    </span>
                                </span>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={busy}
                                    onClick={() => onDealFile(f.uuid)}
                                >
                                    {t('contracts.web.review')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
