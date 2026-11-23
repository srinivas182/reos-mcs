import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { nativeSelect } from '@/lib/tenants';
import { update } from '@/routes/builder/questions';
import type { QuestionLanguage, QuestionRow } from './types';

const blank = (n: number): QuestionLanguage => ({
    prompt: '',
    options: Array.from({ length: n }, () => ''),
    explanation: '',
});

/**
 * Edit a question on screen 45 (A8.1): both languages, the right answer,
 * topic, difficulty and exam portion. `url` saves somewhere else: the
 * starter bank's own questions (A9.1).
 */
export function QuestionDialog({
    question,
    topics,
    difficulties,
    portions,
    url,
    onClose,
}: {
    question: QuestionRow | null;
    topics: string[];
    difficulties: string[];
    portions: string[];
    url?: (id: number) => string;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog
            open={question !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('questions.editTitle')}</DialogTitle>
                </DialogHeader>
                {question ? (
                    <Form
                        key={question.id}
                        question={question}
                        topics={topics}
                        difficulties={difficulties}
                        portions={portions}
                        url={url}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function Form({
    question,
    topics,
    difficulties,
    portions,
    url,
    onDone,
}: {
    question: QuestionRow;
    topics: string[];
    difficulties: string[];
    portions: string[];
    url?: (id: number) => string;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const count = question.en?.options.length ?? 4;
    const [en, setEn] = useState<QuestionLanguage>(question.en ?? blank(count));
    const [es, setEs] = useState<QuestionLanguage>(question.es ?? blank(count));
    const [correct, setCorrect] = useState(question.correct);
    const [topic, setTopic] = useState(question.topic ?? topics[0]);
    const [difficulty, setDifficulty] = useState(
        question.difficulty ?? 'medium',
    );
    const [portion, setPortion] = useState(question.portion ?? 'national');

    const language = (
        code: 'en' | 'es',
        value: QuestionLanguage,
        set: (v: QuestionLanguage) => void,
    ) => (
        <fieldset className="space-y-3" lang={code}>
            <legend className="font-semibold">
                {t(`review.page.${code === 'en' ? 'english' : 'spanish'}`)}
            </legend>
            <div className="grid gap-2">
                <Label htmlFor={`${code}-prompt`}>
                    {t('questions.prompt')}
                </Label>
                <Textarea
                    id={`${code}-prompt`}
                    value={value.prompt}
                    maxLength={400}
                    onChange={(e) => set({ ...value, prompt: e.target.value })}
                />
            </div>
            {value.options.map((option, i) => (
                <div key={i} className="grid gap-2">
                    <Label htmlFor={`${code}-option-${i}`}>
                        {t('questions.option', { n: i + 1 })}
                    </Label>
                    <Input
                        id={`${code}-option-${i}`}
                        value={option}
                        maxLength={200}
                        onChange={(e) =>
                            set({
                                ...value,
                                options: value.options.map((o, j) =>
                                    j === i ? e.target.value : o,
                                ),
                            })
                        }
                    />
                </div>
            ))}
            <div className="grid gap-2">
                <Label htmlFor={`${code}-explanation`}>
                    {t('questions.explanation')}
                </Label>
                <Textarea
                    id={`${code}-explanation`}
                    value={value.explanation}
                    maxLength={400}
                    onChange={(e) =>
                        set({ ...value, explanation: e.target.value })
                    }
                />
            </div>
        </fieldset>
    );

    return (
        <form
            className="space-y-5"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    url ? url(question.id) : update(question.id).url,
                    { en, es, correct, topic, difficulty, portion },
                    { preserveScroll: true, onSuccess: onDone },
                );
            }}
        >
            <div className="grid gap-6 md:grid-cols-2">
                {language('en', en, setEn)}
                {language('es', es, setEs)}
            </div>
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div className="grid gap-2">
                    <Label htmlFor="q-correct">{t('questions.correct')}</Label>
                    <select
                        id="q-correct"
                        className={nativeSelect}
                        value={correct}
                        onChange={(e) => setCorrect(Number(e.target.value))}
                    >
                        {en.options.map((_, i) => (
                            <option key={i} value={i}>
                                {t('questions.option', { n: i + 1 })}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="q-topic">
                        {t('questions.columns.topic')}
                    </Label>
                    <select
                        id="q-topic"
                        className={nativeSelect}
                        value={topic}
                        onChange={(e) => setTopic(e.target.value)}
                    >
                        {topics.map((v) => (
                            <option key={v} value={v}>
                                {t(`onboarding.topics.${v as 'agency'}`)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="q-difficulty">
                        {t('questions.columns.difficulty')}
                    </Label>
                    <select
                        id="q-difficulty"
                        className={nativeSelect}
                        value={difficulty}
                        onChange={(e) =>
                            setDifficulty(e.target.value as 'easy')
                        }
                    >
                        {difficulties.map((v) => (
                            <option key={v} value={v}>
                                {t(`questions.difficulties.${v as 'easy'}`)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="q-portion">
                        {t('questions.columns.outline')}
                    </Label>
                    <select
                        id="q-portion"
                        className={nativeSelect}
                        value={portion}
                        onChange={(e) =>
                            setPortion(e.target.value as 'national')
                        }
                    >
                        {portions.map((v) => (
                            <option key={v} value={v}>
                                {t(`questions.portions.${v as 'national'}`)}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('questions.cancel')}
                </Button>
                <Button type="submit">{t('questions.save')}</Button>
            </DialogFooter>
        </form>
    );
}
