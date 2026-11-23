import { BookCheck, SendHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { StudentQuestion } from './types';

/**
 * Answer a student (A10.1b, instructor screen i10): the question, what the
 * course content said, and the answer, with Add to course knowledge (on by
 * default: answer once, help every future student) and Make a flashcard.
 */
export function AnswerQuestion({
    question,
    busy,
    error,
    onSend,
}: {
    question: StudentQuestion;
    busy: boolean;
    error: string | null;
    onSend: (values: {
        answer: string;
        add_to_knowledge: boolean;
        flashcard: boolean;
    }) => void;
}) {
    const { t } = useTranslation();
    const [answer, setAnswer] = useState('');
    const [knowledge, setKnowledge] = useState(true);
    const [flashcard, setFlashcard] = useState(false);

    return (
        <article className="space-y-4 rounded-reos-lg border border-border bg-card p-4">
            <div className="space-y-1">
                <p className="text-sm text-muted-foreground">
                    {t('ask.instructor.askedBy', {
                        student: question.student,
                        course: question.course,
                    })}
                </p>
                <h2 className="text-lg font-semibold">{question.question}</h2>
            </div>
            {question.content_answer ? (
                <div className="space-y-1 rounded-reos-md bg-muted/50 p-3">
                    <p className="text-sm font-semibold text-muted-foreground">
                        {t('ask.instructor.contentSaid')}
                    </p>
                    <p className="text-sm">{question.content_answer}</p>
                </div>
            ) : null}
            {question.status === 'answered' ? (
                <div className="space-y-1">
                    <p className="text-sm font-semibold">
                        {question.answered_by}
                    </p>
                    <p className="text-body whitespace-pre-line">
                        {question.answer}
                    </p>
                    {question.added_to_knowledge ? (
                        <p className="flex items-center gap-2 text-sm text-reos-tone-success-fg">
                            <BookCheck aria-hidden className="size-4" />
                            {t('ask.instructor.addedToKnowledge')}
                        </p>
                    ) : null}
                </div>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onSend({
                            answer: answer.trim(),
                            add_to_knowledge: knowledge,
                            flashcard,
                        });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="student-answer">
                            {t('ask.instructor.yourAnswer')}
                        </Label>
                        <Textarea
                            id="student-answer"
                            rows={5}
                            maxLength={4000}
                            value={answer}
                            onChange={(e) => setAnswer(e.target.value)}
                        />
                    </div>
                    <div className="flex min-h-12 items-center gap-3">
                        <Checkbox
                            id="answer-knowledge"
                            checked={knowledge}
                            onCheckedChange={(on) => setKnowledge(on === true)}
                        />
                        <Label htmlFor="answer-knowledge">
                            {t('ask.instructor.addToKnowledge')}
                        </Label>
                    </div>
                    <div className="flex min-h-12 items-center gap-3">
                        <Checkbox
                            id="answer-flashcard"
                            checked={flashcard}
                            onCheckedChange={(on) => setFlashcard(on === true)}
                        />
                        <Label htmlFor="answer-flashcard">
                            {t('ask.instructor.flashcard')}
                        </Label>
                    </div>
                    {error ? <InputError message={error} /> : null}
                    <Button
                        type="submit"
                        disabled={busy || answer.trim().length < 2}
                    >
                        <SendHorizontal aria-hidden />
                        {t('ask.instructor.send')}
                    </Button>
                </form>
            )}
        </article>
    );
}
