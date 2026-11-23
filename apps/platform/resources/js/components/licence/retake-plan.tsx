import { RotateCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { Journey } from '@/components/licence/types';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';

/**
 * The retake plan (screen 19): the passed portion is kept; drills on the
 * missed portion's weak areas (from the score report, then readiness);
 * attempts left before more education.
 */
export function RetakePlan({
    retake,
    onStart,
}: {
    retake: NonNullable<Journey['retake']>;
    onStart: (topic: string) => void;
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby="retake-plan"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2
                id="retake-plan"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <RotateCcw aria-hidden className="size-5" />
                {t('licence.retake.title')}
            </h2>
            <p className="text-base">
                {t('licence.retake.body', {
                    portions: retake.portions
                        .map((p) => t(`licence.portions.${p}`))
                        .join(', '),
                })}
            </p>
            {retake.more_education ? (
                <Alert variant="destructive">
                    <AlertDescription className="text-base">
                        {t('licence.retake.moreEducation')}
                    </AlertDescription>
                </Alert>
            ) : (
                <p className="text-base text-muted-foreground">
                    {retake.portions
                        .map((p) =>
                            t('licence.retake.attemptsLeft', {
                                portion: t(`licence.portions.${p}`),
                                count: retake.attempts_left[p] ?? 0,
                            }),
                        )
                        .join(' ')}
                </p>
            )}
            <ul className="space-y-2">
                {retake.topics.map((topic) => (
                    <li
                        key={topic}
                        className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md border border-border p-3"
                    >
                        <span className="text-base font-medium">
                            {t(
                                `practice.topics.${topic as 'agency' | 'contracts' | 'ownership' | 'finance' | 'math' | 'state_rules'}`,
                            )}
                        </span>
                        <Button onClick={() => onStart(topic)}>
                            {t('licence.retake.start')}
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
