import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    Handshake,
    PencilLine,
    Send,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { SourcesList } from './sources-list';
import type { BrokerQuestion } from './types';

/**
 * One question and its answer (agent screens 61 and 62, web screen 16):
 * the answer with its confidence and sources; "With your broker" and the
 * expected time when it went to the broker, with why; the broker's reply
 * labelled "From your broker", Confirmed or Corrected; Send to my broker.
 * Guidance from the brokerage's policies, not legal advice.
 */
export function AnswerCard({
    question,
    onEscalate,
    busy = false,
    dealHref,
}: {
    question: BrokerQuestion;
    onEscalate?: () => void;
    busy?: boolean;
    dealHref?: (uuid: string) => string;
}) {
    const { t, i18n } = useTranslation();
    const time = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(i18n.language, {
                  weekday: 'short',
                  hour: 'numeric',
                  minute: '2-digit',
              }).format(new Date(value))
            : '';
    const replied =
        question.status === 'confirmed' || question.status === 'corrected';

    return (
        <article className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <header className="space-y-1">
                <p className="text-sm text-muted-foreground">
                    {t('virtualBroker.youAsked')}
                </p>
                <h2 className="text-lg font-semibold">{question.question}</h2>
                {question.deal ? (
                    <a
                        href={dealHref ? dealHref(question.deal.uuid) : '#'}
                        className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <Handshake aria-hidden className="size-4" />
                        {question.deal.property}
                    </a>
                ) : null}
            </header>

            {replied ? (
                <section className="space-y-2 rounded-reos-md border border-reos-tone-success-fg/30 bg-reos-tone-success-bg p-4">
                    <p className="flex flex-wrap items-center gap-2 text-base font-semibold">
                        {question.status === 'confirmed' ? (
                            <CheckCircle2
                                aria-hidden
                                className="size-5 text-reos-tone-success-fg"
                            />
                        ) : (
                            <PencilLine
                                aria-hidden
                                className="size-5 text-reos-tone-success-fg"
                            />
                        )}
                        {t('virtualBroker.fromBroker', {
                            broker: question.broker ?? '',
                        })}
                        <Badge variant="secondary">
                            {t(
                                question.status === 'confirmed'
                                    ? 'virtualBroker.outcome.confirmed'
                                    : 'virtualBroker.outcome.corrected',
                            )}
                        </Badge>
                    </p>
                    <p className="text-base whitespace-pre-line">
                        {question.broker_answer}
                    </p>
                </section>
            ) : null}

            <section className="space-y-2">
                <p className="flex flex-wrap items-center gap-2 text-base font-semibold">
                    {replied
                        ? t('virtualBroker.aiAnswerWas')
                        : t('virtualBroker.aiAnswer')}
                    <Badge
                        variant={
                            question.confidence === 'high'
                                ? 'default'
                                : 'outline'
                        }
                    >
                        {t(`virtualBroker.confidence.${question.confidence}`)}
                    </Badge>
                </p>
                <p
                    className={
                        replied
                            ? 'text-base whitespace-pre-line text-muted-foreground'
                            : 'text-base whitespace-pre-line'
                    }
                >
                    {question.answer}
                </p>
            </section>

            {question.status === 'waiting' ? (
                <p
                    role="status"
                    className="flex flex-wrap items-center gap-2 rounded-reos-md border border-reos-tone-warning-fg/30 bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg"
                >
                    <Clock aria-hidden className="size-5" />
                    <span className="font-semibold">
                        {t('virtualBroker.withBroker')}
                    </span>
                    {question.due_at ? (
                        <span>
                            {t('virtualBroker.expected', {
                                time: time(question.due_at),
                            })}
                        </span>
                    ) : null}
                    {question.reason ? (
                        <span>
                            · {t(`virtualBroker.reasons.${question.reason}`)}
                        </span>
                    ) : null}
                </p>
            ) : null}

            {question.status === 'answered' &&
            question.confidence !== 'high' ? (
                <p className="flex items-center gap-2 text-base text-muted-foreground">
                    <AlertTriangle aria-hidden className="size-5" />
                    {t('virtualBroker.checkWithBroker')}
                </p>
            ) : null}

            <SourcesList sources={question.sources} />

            <footer className="flex flex-wrap items-center justify-between gap-3 border-t border-border pt-3">
                <p className="text-sm text-muted-foreground">
                    {t('virtualBroker.notLegalAdvice')}
                </p>
                {question.status === 'answered' && onEscalate ? (
                    <Button
                        type="button"
                        variant="outline"
                        onClick={onEscalate}
                        disabled={busy}
                    >
                        <Send aria-hidden />
                        {t('virtualBroker.sendToBroker')}
                    </Button>
                ) : null}
            </footer>
        </article>
    );
}
