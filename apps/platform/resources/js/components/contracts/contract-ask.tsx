import { Copy, SendHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ContractQuestion } from './types';

/** Ask about this contract (agent screen 66): answers citing pages, and a note for the client to copy. */
export function ContractAsk({
    questions,
    onAsk,
    busy = false,
    cost,
}: {
    questions: ContractQuestion[];
    onAsk: (q: string) => void | Promise<void>;
    busy?: boolean;
    cost: number;
}) {
    const { t } = useTranslation();
    const [text, setText] = useState('');

    return (
        <section
            aria-labelledby="cr-ask"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="cr-ask" className="text-lg font-semibold">
                {t('contracts.web.askTitle')}
            </h2>
            {questions.map((q) => (
                <article
                    key={q.asked_at + q.question}
                    className="space-y-2 border-b border-border pb-4"
                >
                    <p className="text-base font-semibold">{q.question}</p>
                    <p className="text-base whitespace-pre-line">{q.answer}</p>
                    <div className="space-y-2 rounded-reos-md bg-muted/40 p-3">
                        <p className="text-sm font-semibold">
                            {t('contracts.web.noteTitle')}
                        </p>
                        <p className="text-base">{q.note}</p>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                void navigator.clipboard?.writeText(q.note);
                                toast.success(t('contracts.web.copied'));
                            }}
                        >
                            <Copy aria-hidden />
                            {t('contracts.web.copy')}
                        </Button>
                    </div>
                </article>
            ))}
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    if (text.trim().length >= 3) {
                        void Promise.resolve(onAsk(text.trim())).then(() =>
                            setText(''),
                        );
                    }
                }}
            >
                <Label htmlFor="cr-question">
                    {t('contracts.web.askLabel')}
                </Label>
                <Textarea
                    id="cr-question"
                    rows={2}
                    maxLength={500}
                    value={text}
                    onChange={(e) => setText(e.target.value)}
                    placeholder={t('contracts.web.askPlaceholder')}
                />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {t('contracts.web.askCost', { count: cost })}
                    </p>
                    <Button
                        type="submit"
                        disabled={busy || text.trim().length < 3}
                    >
                        <SendHorizontal aria-hidden />
                        {t('contracts.web.ask')}
                    </Button>
                </div>
            </form>
        </section>
    );
}
