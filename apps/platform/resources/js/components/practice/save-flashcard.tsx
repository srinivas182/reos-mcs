import { BookmarkCheck, BookmarkPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { store } from '@/routes/practice/flashcards';

/**
 * Save as flashcard (A9.2d): from a check-in, an Ask answer or answer
 * review. The card is due at once and joins the next burst.
 */
export function SaveFlashcard({
    source,
    front,
    back,
    questionId,
}: {
    source: 'checkin' | 'ask' | 'review';
    front: string;
    back: string;
    questionId?: number;
}) {
    const { t } = useTranslation();
    const [state, setState] = useState<'idle' | 'saving' | 'saved' | 'failed'>(
        'idle',
    );

    const save = async () => {
        setState('saving');
        try {
            await postJson(store().url, {
                source,
                front,
                back,
                ...(questionId ? { question_id: questionId } : {}),
            });
            setState('saved');
        } catch {
            setState('failed');
        }
    };

    if (state === 'saved') {
        return (
            <p
                role="status"
                className="inline-flex items-center gap-2 text-body text-muted-foreground"
            >
                <BookmarkCheck aria-hidden className="size-5" />
                {t('practice.flashcards.saved')}
            </p>
        );
    }

    return (
        <>
            <Button
                type="button"
                variant="outline"
                disabled={state === 'saving'}
                onClick={() => void save()}
            >
                <BookmarkPlus aria-hidden />
                {t('practice.flashcards.save')}
            </Button>
            {state === 'failed' ? (
                <p role="alert" className="text-body text-destructive">
                    {t('practice.errors.save')}
                </p>
            ) : null}
        </>
    );
}
