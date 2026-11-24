import {
    BookOpen,
    FileUp,
    Gavel,
    MessageSquareQuote,
    Trash2,
} from 'lucide-react';
import { useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import type { BrokerVirtualBroker } from './types';

/**
 * Teach the virtual broker (broker web screen 34; broker journey screen 5):
 * the manual with its pages and topics, upload a new version (every version
 * is kept), conflicts with TREC rules (the TREC rule wins), the FAQ from
 * the broker's answers, and who answers escalations and how fast.
 */
export function PolicyKnowledge({
    data,
    onUpload,
    onRemoveFaq,
    onSaveSettings,
    busy = false,
}: {
    data: BrokerVirtualBroker;
    onUpload: (file: File) => void;
    onRemoveFaq: (id: number) => void;
    onSaveSettings: (answererId: number | null, replyHours: number) => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const file = useRef<HTMLInputElement>(null);
    const [answerer, setAnswerer] = useState<string>(
        data.settings.answerer_id === null
            ? ''
            : String(data.settings.answerer_id),
    );
    const [hours, setHours] = useState(String(data.settings.reply_hours));
    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
              }).format(new Date(value))
            : '';
    const manual = data.manual;

    return (
        <div className="space-y-6">
            <section
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                aria-labelledby="vb-manual"
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h2 id="vb-manual" className="text-lg font-semibold">
                            {t('virtualBroker.broker.manual')}
                        </h2>
                        {manual ? (
                            <p className="text-base text-muted-foreground">
                                {t('virtualBroker.broker.manualLine', {
                                    file: manual.file_name,
                                    version: manual.version,
                                    pages: manual.pages,
                                    passages: manual.passages,
                                    date: date(manual.uploaded_at),
                                })}
                            </p>
                        ) : null}
                    </div>
                    <input
                        ref={file}
                        type="file"
                        accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown"
                        className="sr-only"
                        aria-label={t('virtualBroker.broker.upload')}
                        onChange={(e) => {
                            const chosen = e.target.files?.[0];
                            if (chosen) onUpload(chosen);
                            e.target.value = '';
                        }}
                    />
                    <Button
                        type="button"
                        variant={manual ? 'outline' : 'default'}
                        disabled={busy}
                        onClick={() => file.current?.click()}
                    >
                        <FileUp aria-hidden />
                        {manual
                            ? t('virtualBroker.broker.replace')
                            : t('virtualBroker.broker.upload')}
                    </Button>
                </div>
                {manual ? (
                    <>
                        <div
                            className="flex flex-wrap gap-2"
                            aria-label={t('virtualBroker.broker.topics')}
                        >
                            {manual.topics.map((topic) => (
                                <Badge key={topic} variant="secondary">
                                    {t(`virtualBroker.topics.${topic}`)}
                                </Badge>
                            ))}
                        </div>
                        {data.versions.length > 1 ? (
                            <details className="text-base">
                                <summary className="min-h-12 cursor-pointer py-3 font-medium">
                                    {t('virtualBroker.broker.versions', {
                                        count: data.versions.length,
                                    })}
                                </summary>
                                <ul className="space-y-1 text-muted-foreground">
                                    {data.versions.map((v) => (
                                        <li key={v.id}>
                                            {t(
                                                'virtualBroker.broker.versionLine',
                                                {
                                                    version: v.version,
                                                    file: v.file_name,
                                                    date: date(v.uploaded_at),
                                                },
                                            )}
                                            {v.current
                                                ? ` · ${t('virtualBroker.broker.current')}`
                                                : ''}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        ) : null}
                    </>
                ) : (
                    <EmptyState
                        icon={BookOpen}
                        title={t('virtualBroker.broker.noManual')}
                        description={t('virtualBroker.broker.noManualBody')}
                    />
                )}
            </section>

            <section
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                aria-labelledby="vb-conflicts"
            >
                <h2
                    id="vb-conflicts"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Gavel aria-hidden className="size-5" />
                    {t('virtualBroker.broker.conflicts', {
                        count: data.conflicts.length,
                    })}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('virtualBroker.broker.trecWins')}
                </p>
                {data.conflicts.length === 0 ? (
                    <EmptyState
                        icon={Gavel}
                        title={t('virtualBroker.broker.noConflicts')}
                        description={t('virtualBroker.broker.noConflictsBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {data.conflicts.map((c, i) => (
                            <li
                                key={`${c.rule}-${i}`}
                                className="space-y-2 rounded-reos-md border border-reos-tone-danger-fg/30 bg-reos-tone-danger-bg p-4 text-reos-tone-danger-fg"
                            >
                                <p className="text-base font-semibold">
                                    {c.page !== null
                                        ? t('virtualBroker.source.page', {
                                              page: c.page,
                                          })
                                        : t('virtualBroker.source.manual')}
                                    : “{c.excerpt}”
                                </p>
                                <p className="text-base">
                                    <span className="font-semibold">
                                        {c.citation}:
                                    </span>{' '}
                                    {c.rule_text}
                                </p>
                                {c.url ? (
                                    <a
                                        href={c.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="inline-flex min-h-12 items-center font-medium underline underline-offset-4"
                                    >
                                        {t('virtualBroker.source.open')}
                                    </a>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                aria-labelledby="vb-faq"
            >
                <h2
                    id="vb-faq"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <MessageSquareQuote aria-hidden className="size-5" />
                    {t('virtualBroker.broker.faq')}
                </h2>
                {data.faq.length === 0 ? (
                    <EmptyState
                        icon={MessageSquareQuote}
                        title={t('virtualBroker.broker.noFaq')}
                        description={t('virtualBroker.broker.noFaqBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {data.faq.map((f) => (
                            <li
                                key={f.id}
                                className="flex flex-wrap items-start justify-between gap-3 rounded-reos-md border border-border p-4"
                            >
                                <div className="min-w-0 flex-1 space-y-1">
                                    <p className="text-base font-semibold">
                                        {f.question}
                                    </p>
                                    <p className="text-base text-muted-foreground">
                                        {f.answer}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => onRemoveFaq(f.id)}
                                >
                                    <Trash2 aria-hidden />
                                    {t('virtualBroker.broker.remove')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <form
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                aria-labelledby="vb-escalation"
                onSubmit={(e) => {
                    e.preventDefault();
                    onSaveSettings(
                        answerer === '' ? null : Number(answerer),
                        Math.max(1, Math.min(72, Number(hours) || 4)),
                    );
                }}
            >
                <h2 id="vb-escalation" className="text-lg font-semibold">
                    {t('virtualBroker.broker.escalation')}
                </h2>
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="vb-answerer">
                            {t('virtualBroker.broker.answerer')}
                        </Label>
                        <select
                            id="vb-answerer"
                            className={nativeSelect}
                            value={answerer}
                            onChange={(e) => setAnswerer(e.target.value)}
                        >
                            <option value="">
                                {t('virtualBroker.broker.everyBroker')}
                            </option>
                            {data.settings.brokers.map((b) => (
                                <option key={b.id} value={String(b.id)}>
                                    {b.name}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="vb-hours">
                            {t('virtualBroker.broker.replyHours')}
                        </Label>
                        <Input
                            id="vb-hours"
                            type="number"
                            min={1}
                            max={72}
                            inputMode="numeric"
                            value={hours}
                            onChange={(e) => setHours(e.target.value)}
                        />
                    </div>
                </div>
                <Button type="submit" variant="outline" disabled={busy}>
                    {t('virtualBroker.broker.save')}
                </Button>
            </form>
        </div>
    );
}
