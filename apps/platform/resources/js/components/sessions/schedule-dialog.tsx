import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChoiceTiles } from '@/components/choice-tiles';
import InputError from '@/components/input-error';
import type { Contact } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { roleName } from '@/lib/roles';
import { nativeSelect } from '@/lib/tenants';
import { store } from '@/routes/sessions';
import type { RoleName } from '@/types/auth';

type Kind = 'one_on_one' | 'live_class';

/** Tomorrow at 10:00 in the browser's time zone, as date and time fields. */
function tomorrowAtTen(): { date: string; time: string } {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    const pad = (n: number) => String(n).padStart(2, '0');

    return {
        date: `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`,
        time: '10:00',
    };
}

/**
 * Schedule a session (B6.3): a 1-on-1 session or a live class with people
 * you work with, at a time in your own time zone. Booking and payment
 * arrive with the marketplace; this is the instructor's or mentor's form.
 */
export function ScheduleDialog({
    open,
    onOpenChange,
    contacts,
    lengths,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    contacts: Contact[];
    lengths: number[];
}) {
    const { t } = useTranslation();
    const start = tomorrowAtTen();
    const [kind, setKind] = useState<Kind>('one_on_one');
    const [title, setTitle] = useState('');
    const [date, setDate] = useState(start.date);
    const [time, setTime] = useState(start.time);
    const [minutes, setMinutes] = useState(45);
    const [guests, setGuests] = useState<number[]>([]);
    const [agenda, setAgenda] = useState('');
    const [filter, setFilter] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const shown = contacts.filter((c) =>
        c.name.toLowerCase().includes(filter.trim().toLowerCase()),
    );

    const choose = (id: number, on: boolean) =>
        setGuests(
            kind === 'one_on_one'
                ? on
                    ? [id]
                    : []
                : on
                  ? [...guests, id]
                  : guests.filter((g) => g !== id),
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('sessions.schedule.title')}</DialogTitle>
                    <DialogDescription>
                        {t('sessions.schedule.description')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="schedule-session"
                    className="space-y-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(
                            store().url,
                            {
                                kind,
                                title,
                                agenda: agenda.trim() === '' ? null : agenda,
                                starts_at: new Date(
                                    `${date}T${time}`,
                                ).toISOString(),
                                minutes,
                                guests,
                            },
                            {
                                onStart: () => setBusy(true),
                                onFinish: () => setBusy(false),
                                onError: setErrors,
                                onSuccess: () => onOpenChange(false),
                            },
                        );
                    }}
                >
                    <ChoiceTiles<Kind>
                        columns={2}
                        label={t('sessions.schedule.kind')}
                        value={kind}
                        onChange={(next) => {
                            setKind(next);
                            setGuests(guests.slice(0, 1));
                        }}
                        options={[
                            {
                                value: 'one_on_one',
                                label: t('sessions.kinds.one_on_one'),
                                description: t('sessions.schedule.oneOnOne'),
                            },
                            {
                                value: 'live_class',
                                label: t('sessions.kinds.live_class'),
                                description: t('sessions.schedule.liveClass'),
                            },
                        ]}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="session-title">
                            {t('sessions.schedule.name')}
                        </Label>
                        <Input
                            id="session-title"
                            value={title}
                            maxLength={120}
                            placeholder={t('sessions.schedule.namePlaceholder')}
                            onChange={(e) => setTitle(e.target.value)}
                        />
                        <InputError message={errors.title} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="session-date">
                                {t('sessions.schedule.date')}
                            </Label>
                            <Input
                                id="session-date"
                                type="date"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="session-time">
                                {t('sessions.schedule.time')}
                            </Label>
                            <Input
                                id="session-time"
                                type="time"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="session-length">
                                {t('sessions.schedule.length')}
                            </Label>
                            <select
                                id="session-length"
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
                    </div>
                    <InputError message={errors.starts_at ?? errors.minutes} />

                    <fieldset className="grid gap-2">
                        <legend className="mb-2 text-body font-semibold text-foreground">
                            {kind === 'one_on_one'
                                ? t('sessions.schedule.who')
                                : t('sessions.schedule.whoMany')}
                        </legend>
                        {contacts.length > 6 ? (
                            <Input
                                aria-label={t('sessions.schedule.find')}
                                placeholder={t('sessions.schedule.find')}
                                value={filter}
                                onChange={(e) => setFilter(e.target.value)}
                            />
                        ) : null}
                        {contacts.length === 0 ? (
                            <p className="text-base text-muted-foreground">
                                {t('sessions.schedule.noContacts')}
                            </p>
                        ) : (
                            <ul className="grid max-h-64 gap-1 overflow-y-auto">
                                {shown.map((c) => (
                                    <li key={c.id}>
                                        <label
                                            htmlFor={`guest-${c.id}`}
                                            className="flex min-h-12 items-center gap-3 text-base"
                                        >
                                            <Checkbox
                                                id={`guest-${c.id}`}
                                                checked={guests.includes(c.id)}
                                                onCheckedChange={(on) =>
                                                    choose(c.id, on === true)
                                                }
                                            />
                                            <span>
                                                {c.name}
                                                <span className="block text-sm text-muted-foreground">
                                                    {roleName(
                                                        t,
                                                        c.role as RoleName,
                                                    )}{' '}
                                                    · {c.organisation}
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <InputError message={errors.guests} />
                    </fieldset>

                    <div className="grid gap-2">
                        <Label htmlFor="session-agenda">
                            {t('sessions.schedule.agenda')}
                        </Label>
                        <Textarea
                            id="session-agenda"
                            rows={3}
                            value={agenda}
                            onChange={(e) => setAgenda(e.target.value)}
                        />
                    </div>
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
                        form="schedule-session"
                        disabled={
                            busy || guests.length === 0 || title.trim() === ''
                        }
                    >
                        {t('sessions.schedule.submit')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
