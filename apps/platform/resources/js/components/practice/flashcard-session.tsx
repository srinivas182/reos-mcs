import { router } from '@inertiajs/react';
import { Layers } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { reviews } from '@/routes/practice/flashcards';
import { countdown } from './types';
import type { Deck, Flashcard } from './types';

const BURST = 120;

/** What a burst works through: cards due, oldest first, then today's new cards. */
export function burstQueue(deck: Deck, now = Date.now()): Flashcard[] {
    const due = deck.cards
        .filter(
            (c) =>
                !c.new &&
                c.due_at !== null &&
                new Date(c.due_at).getTime() <= now,
        )
        .sort((a, b) => (a.due_at ?? '').localeCompare(b.due_at ?? ''));
    const fresh = deck.cards.filter((c) => c.new).slice(0, deck.new_left);

    return [...due, ...fresh];
}

/**
 * Flashcards (screen 31, A9.2d): a two-minute burst of the cards due, then
 * today's new ones, with a counter. Show the answer, then Again (back in
 * ten minutes, so again in this burst), Hard or Got it; the server
 * schedules each by FSRS.
 */
export function FlashcardSession({ deck }: { deck: Deck }) {
    const { t } = useTranslation();
    const initial = useMemo(() => burstQueue(deck), [deck]);
    const [queue, setQueue] = useState<Flashcard[]>(initial);
    const [started, setStarted] = useState(false);
    const [flipped, setFlipped] = useState(false);
    const [left, setLeft] = useState(BURST);
    const [done, setDone] = useState(0);
    const [error, setError] = useState(false);
    const card = queue[0];
    const over = started && (left === 0 || card === undefined);

    useEffect(() => {
        if (!started || over) return;
        const timer = window.setInterval(
            () => setLeft((s) => Math.max(0, s - 1)),
            1000,
        );

        return () => window.clearInterval(timer);
    }, [started, over]);

    const rate = async (rating: 1 | 2 | 3) => {
        if (!card) return;
        setFlipped(false);
        setDone((n) => n + 1);
        setQueue((q) => (rating === 1 ? [...q.slice(1), card] : q.slice(1)));
        try {
            await postJson(reviews().url, {
                reviews: [
                    {
                        card_id: card.id,
                        rating,
                        reviewed_at: new Date().toISOString(),
                        client_id: crypto.randomUUID(),
                    },
                ],
            });
            setError(false);
        } catch {
            setError(true);
        }
    };

    if (initial.length === 0) {
        return (
            <EmptyState
                icon={Layers}
                title={t('practice.flashcards.emptyTitle')}
                description={t('practice.flashcards.empty')}
            />
        );
    }

    if (!started) {
        return (
            <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                <p className="text-body">
                    {t('practice.flashcards.due', { count: deck.due })} ·{' '}
                    {t('practice.flashcards.newLeft', {
                        count: Math.min(
                            deck.new_left,
                            deck.cards.filter((c) => c.new).length,
                        ),
                    })}
                </p>
                <Button onClick={() => setStarted(true)}>
                    {t('practice.flashcards.start')}
                </Button>
            </section>
        );
    }

    if (over || !card) {
        return (
            <section
                role="status"
                className="space-y-3 rounded-reos-lg border border-border bg-reos-tone-success-bg p-5"
            >
                <h2 className="text-lg font-semibold">
                    {t('practice.flashcards.doneTitle')}
                </h2>
                <p className="text-body">
                    {t('practice.flashcards.doneBody', { count: done })}
                </p>
                <Button onClick={() => router.reload()}>
                    {t('practice.flashcards.another')}
                </Button>
            </section>
        );
    }

    return (
        <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
            <div className="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground">
                <span>
                    {t('practice.flashcards.counter', {
                        n: done + 1,
                        left: queue.length - 1,
                    })}
                </span>
                <span role="timer" aria-live="off">
                    {t('practice.flashcards.timeLeft', {
                        time: countdown(left),
                    })}
                </span>
            </div>
            <div className="min-h-40 space-y-3 rounded-reos-md bg-muted p-5">
                <p className="text-sm font-medium text-muted-foreground">
                    {t('practice.flashcards.front')}
                </p>
                <p className="text-xl font-semibold">{card.front}</p>
                {flipped ? (
                    <>
                        <p className="pt-2 text-sm font-medium text-muted-foreground">
                            {t('practice.flashcards.back')}
                        </p>
                        <p className="text-lg" aria-live="polite">
                            {card.back}
                        </p>
                    </>
                ) : null}
            </div>
            {flipped ? (
                <div className="grid gap-2 sm:grid-cols-3">
                    <Button
                        variant="outline"
                        aria-describedby="again-hint"
                        onClick={() => void rate(1)}
                    >
                        {t('practice.flashcards.again')}
                    </Button>
                    <Button variant="outline" onClick={() => void rate(2)}>
                        {t('practice.flashcards.hard')}
                    </Button>
                    <Button onClick={() => void rate(3)}>
                        {t('practice.flashcards.good')}
                    </Button>
                    <p
                        id="again-hint"
                        className="text-sm text-muted-foreground sm:col-span-3"
                    >
                        {t('practice.flashcards.againHint')}
                    </p>
                </div>
            ) : (
                <Button onClick={() => setFlipped(true)}>
                    {t('practice.flashcards.show')}
                </Button>
            )}
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {t('practice.errors.save')}
                </p>
            ) : null}
        </section>
    );
}
