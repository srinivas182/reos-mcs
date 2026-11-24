import { Send } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { SourcesList } from './sources-list';
import type { BrokerQuestion } from './types';

/**
 * Answer an agent (broker journey screen 12): the question, why it came,
 * the AI draft with sources to send as is (Confirmed) or edited
 * (Corrected), and Add to policy knowledge. Drafts are never sent without
 * the broker's tap; the agent sees "From your broker".
 */
export function BrokerReply({
    question,
    onSend,
    busy = false,
}: {
    question: BrokerQuestion;
    onSend: (answer: string, addToKnowledge: boolean) => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const [text, setText] = useState(question.answer);
    const [add, setAdd] = useState(true);
    const due = question.due_at
        ? new Intl.DateTimeFormat(i18n.language, {
              weekday: 'short',
              hour: 'numeric',
              minute: '2-digit',
          }).format(new Date(question.due_at))
        : null;
    const changed = text.trim() !== question.answer.trim();

    return (
        <article className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <header className="space-y-1">
                <p className="text-sm text-muted-foreground">
                    {t('virtualBroker.broker.asked', { agent: question.agent })}
                    {question.reason
                        ? ` · ${t(`virtualBroker.reasons.${question.reason}`)}`
                        : ''}
                    {due
                        ? ` · ${t('virtualBroker.broker.replyBy', { time: due })}`
                        : ''}
                </p>
                <h2 className="text-lg font-semibold">{question.question}</h2>
                {question.deal ? (
                    <p className="text-base text-muted-foreground">
                        {question.deal.property}
                    </p>
                ) : null}
            </header>
            <div className="space-y-2">
                <Label
                    htmlFor={`reply-${question.id}`}
                    className="text-base font-semibold"
                >
                    {t('virtualBroker.broker.draft')}
                </Label>
                <Textarea
                    id={`reply-${question.id}`}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    rows={6}
                    maxLength={4000}
                />
                <p className="text-sm text-muted-foreground">
                    {changed
                        ? t('virtualBroker.broker.willCorrect')
                        : t('virtualBroker.broker.willConfirm')}
                </p>
            </div>
            <SourcesList sources={question.sources} />
            <div className="flex flex-wrap items-center justify-between gap-4 border-t border-border pt-3">
                <div className="flex items-center gap-3">
                    <Switch
                        id={`add-${question.id}`}
                        checked={add}
                        onCheckedChange={setAdd}
                    />
                    <Label htmlFor={`add-${question.id}`} className="text-base">
                        {t('virtualBroker.broker.addToKnowledge')}
                    </Label>
                </div>
                <Button
                    type="button"
                    disabled={busy || text.trim().length < 2}
                    onClick={() => onSend(text.trim(), add)}
                >
                    <Send aria-hidden />
                    {t('virtualBroker.broker.send')}
                </Button>
            </div>
        </article>
    );
}
