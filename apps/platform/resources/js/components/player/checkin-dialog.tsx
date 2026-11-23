import { useState } from 'react';
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
import type { CheckinAnswer, Checkpoint } from '@/lib/seat-time';
import { postJson } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';
import { report } from '@/routes/learn/questions';

/**
 * A check-in (A8.2): the video stops and seat time waits until it is
 * answered. A question shows whether the answer was right and why, with its
 * source; a wrong answer still counts as present. A presence check asks only
 * "Are you still here?".
 */
export function CheckinDialog({
    checkpoint,
    onAnswer,
}: {
    checkpoint: Checkpoint | null;
    onAnswer: (key: string, choice: number | null) => Promise<CheckinAnswer>;
}) {
    // An answered question stays open with its feedback until Continue, even
    // though the server has already moved on.
    const [held, setHeld] = useState<{
        checkpoint: Checkpoint;
        result: CheckinAnswer;
    } | null>(null);
    const showing = held?.checkpoint ?? checkpoint;

    return (
        // Can't be dismissed: answering is the only way on.
        <Dialog open={showing !== null}>
            <DialogContent
                className="sm:max-w-xl"
                showCloseButton={false}
                onEscapeKeyDown={(e) => e.preventDefault()}
                onInteractOutside={(e) => e.preventDefault()}
            >
                {showing ? (
                    <Body
                        key={showing.key}
                        checkpoint={showing}
                        result={held?.result ?? null}
                        onAnswer={async (key, choice) => {
                            const result = await onAnswer(key, choice);

                            if (showing.question) {
                                setHeld({ checkpoint: showing, result });
                            }

                            return result;
                        }}
                        onContinue={() => setHeld(null)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Body({
    checkpoint,
    result,
    onAnswer,
    onContinue,
}: {
    checkpoint: Checkpoint;
    result: CheckinAnswer | null;
    onAnswer: (key: string, choice: number | null) => Promise<CheckinAnswer>;
    onContinue: () => void;
}) {
    const { t } = useTranslation();
    const [choice, setChoice] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(false);
    const question = checkpoint.question;

    const send = async () => {
        setBusy(true);
        setError(false);

        try {
            await onAnswer(checkpoint.key, choice);
        } catch {
            setError(true);
        } finally {
            setBusy(false);
        }
    };

    if (!question) {
        return (
            <>
                <DialogHeader>
                    <DialogTitle>
                        {t('player.checkin.presenceTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('player.checkin.presenceBody')}
                    </DialogDescription>
                </DialogHeader>
                {error ? (
                    <p className="text-body text-destructive" role="alert">
                        {t('player.errors.checkin')}
                    </p>
                ) : null}
                <DialogFooter>
                    <Button onClick={() => void send()} disabled={busy}>
                        {t('player.checkin.here')}
                    </Button>
                </DialogFooter>
            </>
        );
    }

    return (
        <>
            <DialogHeader>
                <DialogTitle>{t('player.checkin.title')}</DialogTitle>
                <DialogDescription>{question.prompt}</DialogDescription>
            </DialogHeader>
            <fieldset className="space-y-2" disabled={result !== null || busy}>
                <legend className="sr-only">{question.prompt}</legend>
                {question.options.map((option, i) => (
                    <label
                        key={i}
                        className={cn(
                            'flex min-h-12 cursor-pointer items-center gap-3 rounded-reos-md border px-3 py-2 text-body',
                            choice === i
                                ? 'border-primary bg-accent'
                                : 'border-border hover:bg-muted',
                        )}
                    >
                        <input
                            type="radio"
                            name={`checkin-${checkpoint.key}`}
                            className="size-5 accent-primary"
                            checked={choice === i}
                            onChange={() => setChoice(i)}
                        />
                        {option}
                    </label>
                ))}
            </fieldset>
            {result ? (
                <div
                    role="status"
                    className="space-y-1 rounded-reos-md bg-muted p-3 text-body"
                >
                    <p className="font-semibold">
                        {result.correct
                            ? t('player.checkin.right')
                            : t('player.checkin.wrong')}
                    </p>
                    {result.explanation ? <p>{result.explanation}</p> : null}
                    {result.source ? (
                        <p className="text-sm text-muted-foreground">
                            {t('player.checkin.source', {
                                source: result.source,
                            })}
                        </p>
                    ) : null}
                </div>
            ) : null}
            {result ? <Report question={question.id} /> : null}
            {error ? (
                <p className="text-body text-destructive" role="alert">
                    {t('player.errors.checkin')}
                </p>
            ) : null}
            <DialogFooter>
                {result ? (
                    <Button onClick={onContinue}>
                        {t('player.checkin.continue')}
                    </Button>
                ) : (
                    <Button
                        onClick={() => void send()}
                        disabled={busy || choice === null}
                    >
                        {t('player.checkin.answer')}
                    </Button>
                )}
            </DialogFooter>
        </>
    );
}

const REASONS = ['wrong_answer', 'unclear', 'out_of_date', 'other'] as const;

/** Report this question (A8.1): its owner keeps or corrects it, and the learner is told. */
function Report({ question }: { question: number }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [reason, setReason] =
        useState<(typeof REASONS)[number]>('wrong_answer');
    const [sent, setSent] = useState(false);

    if (sent) {
        return (
            <p className="text-body text-muted-foreground" role="status">
                {t('player.report.sent')}
            </p>
        );
    }

    if (!open) {
        return (
            <Button
                variant="ghost"
                className="justify-self-start"
                onClick={() => setOpen(true)}
            >
                {t('player.report.title')}
            </Button>
        );
    }

    return (
        <div className="flex flex-wrap items-end gap-3">
            <label className="flex flex-col gap-1 text-body">
                {t('player.report.title')}
                <select
                    className={`${nativeSelect} w-64`}
                    value={reason}
                    onChange={(e) =>
                        setReason(e.target.value as (typeof REASONS)[number])
                    }
                >
                    {REASONS.map((r) => (
                        <option key={r} value={r}>
                            {t(`player.report.reasons.${r}`)}
                        </option>
                    ))}
                </select>
            </label>
            <Button
                variant="outline"
                onClick={() =>
                    void postJson(report.url(question), { reason })
                        .then(() => setSent(true))
                        .catch(() => setSent(false))
                }
            >
                {t('player.report.send')}
            </Button>
        </div>
    );
}
