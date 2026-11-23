import { Plus, SendHorizontal, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { postJson } from '@/lib/content';
import { tasks as tasksRoute } from '@/routes/bookings';
import { send } from '@/routes/bookings/tasks';
import type { Booking } from './types';

/**
 * Session notes into a plan (instructor screen i16, A10.2a): the action
 * lines from the shared notes, edited, sent as to-dos for the student.
 */
export function SendTasksDialog({
    booking,
    onClose,
    onSent,
}: {
    booking: Booking;
    onClose: () => void;
    onSent: () => void;
}) {
    const { t } = useTranslation();
    const [tasks, setTasks] = useState<{ title: string; due_date: string }[]>(
        [],
    );
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        void fetch(tasksRoute(booking.uuid).url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((r) => r.json() as Promise<{ data: string[] }>)
            .then(({ data }) =>
                setTasks(
                    (data.length > 0 ? data : ['']).map((title) => ({
                        title,
                        due_date: '',
                    })),
                ),
            )
            .catch(() => setTasks([{ title: '', due_date: '' }]));
    }, [booking.uuid]);

    const submit = async () => {
        try {
            await postJson(send(booking.uuid).url, {
                tasks: tasks
                    .filter((x) => x.title.trim().length >= 2)
                    .map((x) => ({
                        title: x.title.trim(),
                        due_date: x.due_date || null,
                    })),
            });
            onSent();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('bookings.tasks.title', { name: booking.student })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('bookings.tasks.intro')}
                    </DialogDescription>
                </DialogHeader>
                <ul className="space-y-2">
                    {tasks.map((task, i) => (
                        <li key={i} className="flex items-center gap-2">
                            <Input
                                aria-label={t('bookings.tasks.label', {
                                    n: i + 1,
                                })}
                                value={task.title}
                                maxLength={200}
                                onChange={(e) =>
                                    setTasks(
                                        tasks.map((x, j) =>
                                            j === i
                                                ? {
                                                      ...x,
                                                      title: e.target.value,
                                                  }
                                                : x,
                                        ),
                                    )
                                }
                            />
                            <Input
                                type="date"
                                className="w-44"
                                aria-label={t('bookings.tasks.due', {
                                    n: i + 1,
                                })}
                                value={task.due_date}
                                onChange={(e) =>
                                    setTasks(
                                        tasks.map((x, j) =>
                                            j === i
                                                ? {
                                                      ...x,
                                                      due_date: e.target.value,
                                                  }
                                                : x,
                                        ),
                                    )
                                }
                            />
                            <Button
                                variant="ghost"
                                aria-label={t('bookings.hours.remove')}
                                onClick={() =>
                                    setTasks(tasks.filter((_, j) => j !== i))
                                }
                            >
                                <X aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
                <Button
                    variant="outline"
                    disabled={tasks.length >= 10}
                    onClick={() =>
                        setTasks([...tasks, { title: '', due_date: '' }])
                    }
                >
                    <Plus aria-hidden />
                    {t('bookings.tasks.add')}
                </Button>
                {error ? <InputError message={error} /> : null}
                <DialogFooter>
                    <Button
                        disabled={
                            tasks.filter((x) => x.title.trim().length >= 2)
                                .length === 0
                        }
                        onClick={() => void submit()}
                    >
                        <SendHorizontal aria-hidden />
                        {t('bookings.tasks.send')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
