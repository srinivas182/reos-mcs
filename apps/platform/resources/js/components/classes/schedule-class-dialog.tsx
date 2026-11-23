import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { ClassCourse } from '@/components/classes/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { store } from '@/routes/classes';

/** Next week's Tuesday-like default: a week from today at 10:00, local time. */
function nextWeekAtTen(): { date: string; time: string } {
    const d = new Date();
    d.setDate(d.getDate() + 7);
    const pad = (n: number) => String(n).padStart(2, '0');

    return {
        date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
        time: '10:00',
    };
}

/**
 * Schedule a live class (A10.2b; Web App Journey w505, instructor i11):
 * a course you teach, the time in your own zone, length and seats. A
 * course with a live approval makes it a CE class with the approval's
 * attendance checks.
 */
export function ScheduleClassDialog({
    open,
    onOpenChange,
    courses,
    lengths,
    onScheduled,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    courses: ClassCourse[];
    lengths: number[];
    onScheduled: () => void;
}) {
    const { t } = useTranslation();
    const start = nextWeekAtTen();
    const [course, setCourse] = useState(courses[0]?.id ?? 0);
    const [title, setTitle] = useState('');
    const [date, setDate] = useState(start.date);
    const [time, setTime] = useState(start.time);
    const [minutes, setMinutes] = useState(
        lengths.includes(60) ? 60 : (lengths[0] ?? 60),
    );
    const [capacity, setCapacity] = useState(30);
    const [agenda, setAgenda] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const chosen = courses.find((c) => c.id === course);

    const submit = async () => {
        setBusy(true);
        setError(null);

        try {
            await postJson(store().url, {
                course_id: course,
                title: title.trim(),
                agenda: agenda.trim() === '' ? null : agenda.trim(),
                starts_at: new Date(`${date}T${time}`).toISOString(),
                minutes,
                capacity,
            });
            onOpenChange(false);
            setTitle('');
            setAgenda('');
            onScheduled();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('classes.schedule.title')}</DialogTitle>
                    <DialogDescription>
                        {t('classes.schedule.description')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="schedule-class"
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void submit();
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="class-course">
                            {t('classes.schedule.course')}
                        </Label>
                        <select
                            id="class-course"
                            className={nativeSelect}
                            value={course}
                            onChange={(e) => setCourse(Number(e.target.value))}
                        >
                            {courses.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.title}
                                </option>
                            ))}
                        </select>
                        <p className="text-sm text-muted-foreground">
                            {chosen?.ce
                                ? t('classes.schedule.ceNote')
                                : t('classes.schedule.noCeNote')}
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="class-title">
                            {t('classes.schedule.name')}
                        </Label>
                        <Input
                            id="class-title"
                            value={title}
                            maxLength={120}
                            placeholder={t('classes.schedule.namePlaceholder')}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="class-date">
                                {t('sessions.schedule.date')}
                            </Label>
                            <Input
                                id="class-date"
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="class-time">
                                {t('sessions.schedule.time')}
                            </Label>
                            <Input
                                id="class-time"
                                type="time"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="class-length">
                                {t('sessions.schedule.length')}
                            </Label>
                            <select
                                id="class-length"
                                className={nativeSelect}
                                value={minutes}
                                onChange={(e) =>
                                    setMinutes(Number(e.target.value))
                                }
                            >
                                {lengths.map((m) => (
                                    <option key={m} value={m}>
                                        {t('sessions.minutes', { count: m })}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="class-capacity">
                                {t('classes.schedule.capacity')}
                            </Label>
                            <Input
                                id="class-capacity"
                                type="number"
                                min={1}
                                max={100}
                                value={capacity}
                                onChange={(e) =>
                                    setCapacity(Number(e.target.value))
                                }
                            />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="class-agenda">
                            {t('sessions.schedule.agenda')}
                        </Label>
                        <Textarea
                            id="class-agenda"
                            rows={3}
                            value={agenda}
                            onChange={(e) => setAgenda(e.target.value)}
                        />
                    </div>
                    <InputError message={error ?? undefined} />
                </form>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        form="schedule-class"
                        disabled={
                            busy || title.trim().length < 3 || course === 0
                        }
                    >
                        {t('classes.schedule.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
