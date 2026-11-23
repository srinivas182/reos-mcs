import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { seatTime } from '@/routes/builder';
import type { BuilderCourse } from './types';

/** Hours set by an approval: instructors ask the school, which files the change with TREC (screen 43). */
export function SeatTimeNotice({ course }: { course: BuilderCourse }) {
    const { t } = useTranslation();
    const [asking, setAsking] = useState(false);
    const [draft, setDraft] = useState({
        hours: String(course.hours ?? ''),
        reason: '',
    });

    if (!course.hours_locked) {
        return null;
    }

    return (
        <section className="space-y-3 rounded-reos-lg bg-reos-tone-info-bg p-4 text-reos-tone-info-fg">
            <p className="text-base">
                {t('builder.seatTime.locked', {
                    hours: course.hours ?? 0,
                    number: course.approval ?? '',
                })}
            </p>
            {asking ? (
                <form
                    className="space-y-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(
                            seatTime(course.id).url,
                            {
                                hours: Number(draft.hours),
                                reason: draft.reason,
                            },
                            {
                                preserveScroll: true,
                                onSuccess: () => setAsking(false),
                            },
                        );
                    }}
                >
                    <div className="grid max-w-40 gap-2">
                        <Label htmlFor="seat-hours">
                            {t('builder.seatTime.hours')}
                        </Label>
                        <Input
                            id="seat-hours"
                            type="number"
                            min={0.5}
                            step="0.5"
                            value={draft.hours}
                            onChange={(e) =>
                                setDraft({ ...draft, hours: e.target.value })
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="seat-reason">
                            {t('builder.seatTime.reason')}
                        </Label>
                        <Textarea
                            id="seat-reason"
                            maxLength={500}
                            value={draft.reason}
                            onChange={(e) =>
                                setDraft({ ...draft, reason: e.target.value })
                            }
                        />
                    </div>
                    <Button
                        type="submit"
                        disabled={draft.reason.trim().length < 5}
                    >
                        {t('builder.seatTime.send')}
                    </Button>
                </form>
            ) : (
                <Button variant="outline" onClick={() => setAsking(true)}>
                    {t('builder.seatTime.ask')}
                </Button>
            )}
        </section>
    );
}
