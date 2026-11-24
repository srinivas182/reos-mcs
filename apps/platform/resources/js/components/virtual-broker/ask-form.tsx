import { SendHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** Ask the virtual broker (web screen 16): a question in plain words. */
export function AskForm({
    brokerage,
    onAsk,
    busy = false,
    error = null,
}: {
    brokerage: string;
    onAsk: (question: string) => void | Promise<void>;
    busy?: boolean;
    error?: string | null;
}) {
    const { t } = useTranslation();
    const [text, setText] = useState('');

    return (
        <form
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                if (text.trim().length >= 3) {
                    void Promise.resolve(onAsk(text.trim())).then(() =>
                        setText(''),
                    );
                }
            }}
        >
            <Label htmlFor="vb-question" className="text-base font-semibold">
                {t('virtualBroker.askLabel', { brokerage })}
            </Label>
            <Textarea
                id="vb-question"
                value={text}
                onChange={(e) => setText(e.target.value)}
                rows={3}
                maxLength={1000}
                placeholder={t('virtualBroker.placeholder')}
            />
            {error ? (
                <p role="alert" className="text-base text-destructive">
                    {error}
                </p>
            ) : null}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-muted-foreground">
                    {t('virtualBroker.free')}
                </p>
                <Button type="submit" disabled={busy || text.trim().length < 3}>
                    <SendHorizontal aria-hidden />
                    {busy ? t('virtualBroker.asking') : t('virtualBroker.ask')}
                </Button>
            </div>
        </form>
    );
}
