import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChoiceTiles } from '@/components/choice-tiles';
import type { ExamKind, HubTest } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { start } from '@/routes/practice';

/**
 * Test intro (candidate screen 40, A9.1): questions, time, pass line, when
 * feedback comes and how flag, calculator and review work, then Exam mode
 * (feedback at the end, no pause) or Practice mode (why after each answer,
 * pause allowed).
 */
export function TestIntroDialog({
    test,
    kind,
    label,
}: {
    test: HubTest;
    kind: ExamKind;
    label: string;
}) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<'exam' | 'practice'>('exam');
    const [starting, setStarting] = useState(false);

    return (
        <Dialog>
            <DialogTrigger asChild>
                <Button>{label}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t(`practice.portions.${kind}.title`)}
                    </DialogTitle>
                    <DialogDescription>
                        {t('practice.intro.format', {
                            questions: test.questions,
                            minutes: test.minutes ?? 0,
                        })}
                    </DialogDescription>
                </DialogHeader>
                <ul className="list-disc space-y-1 pl-5 text-body">
                    <li>
                        {t(
                            `practice.intro.pass.${kind === 'simulation' ? 'both' : kind}`,
                        )}
                    </li>
                    {kind === 'simulation' ? (
                        <li>{t('practice.intro.portions')}</li>
                    ) : null}
                    <li>{t('practice.intro.tools')}</li>
                    <li>{t('practice.intro.pretest')}</li>
                </ul>
                <ChoiceTiles<'exam' | 'practice'>
                    label={t('practice.intro.modeLabel')}
                    value={mode}
                    onChange={setMode}
                    options={[
                        {
                            value: 'exam',
                            label: t('practice.intro.exam'),
                            description: t('practice.intro.examHint'),
                        },
                        {
                            value: 'practice',
                            label: t('practice.intro.practice'),
                            description: t('practice.intro.practiceHint'),
                        },
                    ]}
                />
                <DialogFooter>
                    <Button
                        disabled={starting}
                        onClick={() => {
                            setStarting(true);
                            router.post(
                                start().url,
                                { kind, mode },
                                { onFinish: () => setStarting(false) },
                            );
                        }}
                    >
                        {t('practice.intro.start')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
