import { CheckCircle2, Circle, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { dayText, type Journey } from '@/components/licence/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * The state's steps (screen 16): what to do, the regulator's page, and the
 * candidate's own check-off with its date. The exam checks itself off once
 * both portions are passed.
 */
export function StepsList({
    steps,
    onCheck,
}: {
    steps: Journey['steps'];
    onCheck: (code: string, done: boolean, on?: string) => void;
}) {
    const { t, i18n } = useTranslation();

    return (
        <ol className="space-y-3">
            {steps.map((step, i) => {
                const done = step.done_on !== null;
                const Icon = done ? CheckCircle2 : Circle;

                return (
                    <li
                        key={step.code}
                        className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="flex min-w-0 items-start gap-3">
                                <Icon
                                    aria-hidden
                                    className={
                                        done
                                            ? 'mt-0.5 size-6 shrink-0 text-reos-tone-success-fg'
                                            : 'mt-0.5 size-6 shrink-0 text-muted-foreground'
                                    }
                                />
                                <div className="min-w-0 space-y-1">
                                    <h3 className="text-base font-semibold">
                                        {t('licence.stepNumber', {
                                            number: i + 1,
                                            title: step.title,
                                        })}
                                    </h3>
                                    <p className="text-base text-muted-foreground">
                                        {step.detail}
                                    </p>
                                    {done && step.done_on ? (
                                        <p className="text-sm font-medium text-reos-tone-success-fg">
                                            {step.auto
                                                ? t('licence.doneAuto', {
                                                      date: dayText(
                                                          step.done_on,
                                                          i18n.language,
                                                      ),
                                                  })
                                                : t('licence.doneOn', {
                                                      date: dayText(
                                                          step.done_on,
                                                          i18n.language,
                                                      ),
                                                  })}
                                        </p>
                                    ) : null}
                                </div>
                            </div>
                            {step.url ? (
                                <Button asChild variant="ghost">
                                    <a
                                        href={step.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        aria-label={t('licence.openLink', {
                                            title: step.title,
                                        })}
                                    >
                                        <ExternalLink aria-hidden />
                                        {t('licence.whatToDo')}
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                        {step.auto ? null : (
                            <div className="flex flex-wrap items-end gap-3">
                                <div className="grid gap-2">
                                    <Label htmlFor={`step-${step.code}`}>
                                        {t('licence.doneOnLabel')}
                                    </Label>
                                    <Input
                                        id={`step-${step.code}`}
                                        type="date"
                                        className="w-48"
                                        value={step.done_on ?? ''}
                                        onChange={(e) =>
                                            e.target.value
                                                ? onCheck(
                                                      step.code,
                                                      true,
                                                      e.target.value,
                                                  )
                                                : undefined
                                        }
                                    />
                                </div>
                                <Button
                                    variant={done ? 'outline' : 'default'}
                                    onClick={() => onCheck(step.code, !done)}
                                >
                                    {done
                                        ? t('licence.undo', {
                                              title: step.title,
                                          })
                                        : t('licence.markDone')}
                                </Button>
                            </div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
