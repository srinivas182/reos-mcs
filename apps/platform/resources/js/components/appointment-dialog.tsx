import { router } from '@inertiajs/react';
import { ExternalLink, History, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import {
    blankForm,
    eventTitle,
    eventWhen,
    formFromEvent,
    KINDS,
    payload,
    shortDate,
    sourceName,
} from '@/lib/calendar';
import type { AppointmentForm } from '@/lib/calendar';
import { undoToast } from '@/lib/undo-toast';
import {
    destroy,
    restore,
    retry,
    store,
    undo,
    update,
} from '@/routes/calendar';
import type { CalendarDay, CalendarEvent } from '@/types/calendar';

type Props = {
    /** The event to show, `'new'` for a new appointment, or null when closed. */
    event: CalendarEvent | 'new' | null;
    day: Pick<CalendarDay, 'date' | 'timezone' | 'write_to'>;
    onClose: () => void;
};

type Errors = Partial<Record<string, string>>;

/**
 * An appointment on the agent dashboard (screen 12, "open any item"; B4.1).
 *
 * - New, or one that can be changed: the form. All-day events take dates;
 *   guests are only emailed when the person turns it on.
 * - One that can't (busy only, a read-only calendar): what it is, where it
 *   lives, and Open in the tool.
 * - Changed in REOS and in the tool: which version was kept, the other one,
 *   and Undo until the trail ends (30 days).
 * - Not yet saved to the tool: Try again. Deleting (REOS's own only) shows
 *   Undo instead of asking first.
 */
export function AppointmentDialog({ event, day, onClose }: Props) {
    if (event === null) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <Body
                    key={event === 'new' ? 'new' : event.id}
                    event={event}
                    day={day}
                    onClose={onClose}
                />
            </DialogContent>
        </Dialog>
    );
}

function Body({
    event,
    day,
    onClose,
}: {
    event: CalendarEvent | 'new';
    day: Props['day'];
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const isNew = event === 'new';
    const [form, setForm] = useState<AppointmentForm>(() =>
        isNew
            ? blankForm(day.date, day.timezone)
            : formFromEvent(event, day.timezone),
    );
    const [errors, setErrors] = useState<Errors>({});
    const [saving, setSaving] = useState(false);
    const set = <K extends keyof AppointmentForm>(
        key: K,
        value: AppointmentForm[K],
    ) => setForm((current) => ({ ...current, [key]: value }));

    if (!isNew && !event.editable) {
        return <ReadOnly event={event} timeZone={day.timezone} />;
    }

    // A to-do's due date keeps its kind; other REOS events choose one.
    const madeInReos =
        isNew || (event.source === 'reos' && event.kind !== 'todo');
    const submit = (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        const body = payload(form, day.timezone, madeInReos);
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: (bag: Errors) => setErrors(bag),
            onFinish: () => setSaving(false),
        };

        if (isNew) {
            router.post(store().url, body, options);
        } else {
            router.put(update(event.id).url, body, options);
        }
    };
    const remove = () => {
        if (isNew) {
            return;
        }

        router.delete(destroy(event.id).url, {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                undoToast({
                    message: t('calendar.deleted', {
                        title: eventTitle(event, t),
                    }),
                    onUndo: () =>
                        router.post(
                            restore(event.id).url,
                            {},
                            { preserveScroll: true },
                        ),
                });
            },
        });
    };
    const error = (key: string) => errors[key];

    return (
        <form onSubmit={submit} className="space-y-5" noValidate>
            <DialogHeader>
                <DialogTitle>
                    {isNew
                        ? t('calendar.newAppointment')
                        : eventTitle(event, t)}
                </DialogTitle>
                <DialogDescription>
                    {isNew
                        ? day.write_to
                            ? t('calendar.writeTo', {
                                  calendar: day.write_to.calendar,
                                  tool: day.write_to.tool,
                              })
                            : t('calendar.writeToNone')
                        : event.tool && event.calendar
                          ? t('calendar.inCalendar', {
                                calendar: event.calendar,
                                tool: event.tool,
                            })
                          : t('calendar.sync.local')}
                </DialogDescription>
            </DialogHeader>

            {!isNew && event.conflict ? (
                <ConflictNotice event={event} timeZone={day.timezone} />
            ) : null}
            {!isNew && event.sync === 'failed' ? (
                <Alert>
                    <AlertTitle>
                        {t('calendar.sync.failed', { tool: event.tool })}
                    </AlertTitle>
                    <AlertDescription>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    retry(event.id).url,
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw aria-hidden className="size-5" />
                            {t('calendar.tryAgain')}
                        </Button>
                    </AlertDescription>
                </Alert>
            ) : null}
            <InputError message={error('event')} />

            <div className="space-y-2">
                <Label htmlFor="appointment-title">
                    {t('calendar.fields.title')}
                </Label>
                <Input
                    id="appointment-title"
                    value={form.title}
                    placeholder={t('calendar.fields.titlePlaceholder')}
                    onChange={(e) => set('title', e.target.value)}
                    aria-invalid={error('title') ? true : undefined}
                    required
                />
                <InputError message={error('title')} />
            </div>

            {madeInReos ? (
                <div className="space-y-2">
                    <Label htmlFor="appointment-kind">
                        {t('calendar.fields.kind')}
                    </Label>
                    <Select
                        value={form.kind}
                        onValueChange={(value) =>
                            set('kind', value as AppointmentForm['kind'])
                        }
                    >
                        <SelectTrigger id="appointment-kind" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {KINDS.map((kind) => (
                                <SelectItem key={kind} value={kind}>
                                    {t(`calendar.kinds.${kind}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            ) : null}

            <div className="flex items-center justify-between gap-4">
                <Label htmlFor="appointment-all-day">
                    {t('calendar.allDay')}
                </Label>
                <Switch
                    id="appointment-all-day"
                    checked={form.allDay}
                    onCheckedChange={(value) => set('allDay', value)}
                />
            </div>

            {form.allDay ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="appointment-date"
                        label={t('calendar.fields.startDate')}
                        type="date"
                        value={form.date}
                        onChange={(value) => set('date', value)}
                        error={error('start_date')}
                    />
                    <Field
                        id="appointment-end-date"
                        label={t('calendar.fields.endDate')}
                        type="date"
                        value={form.endDate}
                        onChange={(value) => set('endDate', value)}
                        error={error('end_date')}
                    />
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field
                        id="appointment-date"
                        label={t('calendar.fields.date')}
                        type="date"
                        value={form.date}
                        onChange={(value) => set('date', value)}
                        error={error('starts_at')}
                    />
                    <Field
                        id="appointment-start"
                        label={t('calendar.fields.start')}
                        type="time"
                        value={form.start}
                        onChange={(value) => set('start', value)}
                    />
                    <Field
                        id="appointment-end"
                        label={t('calendar.fields.end')}
                        type="time"
                        value={form.end}
                        onChange={(value) => set('end', value)}
                        error={error('ends_at')}
                    />
                </div>
            )}

            <Field
                id="appointment-location"
                label={t('calendar.fields.location')}
                value={form.location}
                onChange={(value) => set('location', value)}
                error={error('location')}
            />

            <div className="space-y-2">
                <Label htmlFor="appointment-guests">
                    {t('calendar.fields.guests')}
                </Label>
                <Input
                    id="appointment-guests"
                    type="text"
                    inputMode="email"
                    value={form.guests}
                    onChange={(e) => set('guests', e.target.value)}
                    aria-describedby="appointment-guests-hint"
                />
                <p
                    id="appointment-guests-hint"
                    className="text-sm text-muted-foreground"
                >
                    {t('calendar.fields.guestsHint')}
                </p>
                <InputError
                    message={
                        Object.entries(errors).find(([key]) =>
                            key.startsWith('attendees'),
                        )?.[1]
                    }
                />
            </div>

            {form.guests.trim() !== '' ? (
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <Label htmlFor="appointment-notify">
                            {t('calendar.fields.notifyGuests')}
                        </Label>
                        <p className="text-sm text-muted-foreground">
                            {t('calendar.fields.notifyHint')}
                        </p>
                    </div>
                    <Switch
                        id="appointment-notify"
                        checked={form.notify}
                        onCheckedChange={(value) => set('notify', value)}
                    />
                </div>
            ) : null}

            <div className="space-y-2">
                <Label htmlFor="appointment-notes">
                    {t('calendar.fields.notes')}
                </Label>
                <Textarea
                    id="appointment-notes"
                    value={form.notes}
                    onChange={(e) => set('notes', e.target.value)}
                    rows={3}
                />
            </div>

            <DialogFooter className="flex-wrap gap-2 sm:justify-between">
                <div className="flex flex-wrap gap-2">
                    {!isNew && event.deletable ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onClick={remove}
                        >
                            {t('calendar.delete')}
                        </Button>
                    ) : null}
                    {!isNew && event.link && event.tool ? (
                        <Button type="button" variant="ghost" asChild>
                            <a
                                href={event.link}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <ExternalLink aria-hidden className="size-5" />
                                {t('calendar.openIn', { tool: event.tool })}
                            </a>
                        </Button>
                    ) : null}
                </div>
                <Button type="submit" disabled={saving}>
                    {isNew ? t('calendar.add') : t('calendar.save')}
                </Button>
            </DialogFooter>
            {!isNew && !event.deletable && event.tool ? (
                <p className="text-sm text-muted-foreground">
                    {t('calendar.readOnly.notOurs', { tool: event.tool })}
                </p>
            ) : null}
        </form>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    error,
    type = 'text',
}: {
    id: string;
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: string;
}) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type={type}
                value={value}
                onChange={(e) => onChange(e.target.value)}
                aria-invalid={error ? true : undefined}
            />
            <InputError message={error} />
        </div>
    );
}

/** Changed in two places: what was kept, the other version, and Undo. */
function ConflictNotice({
    event,
    timeZone,
}: {
    event: CalendarEvent;
    timeZone: string;
}) {
    const { t, i18n } = useTranslation();
    const conflict = event.conflict;

    if (!conflict) {
        return null;
    }

    const tool = event.tool ?? t('calendar.reos');
    const losing = conflict.losing;
    const title =
        conflict.reason === 'deleted_in_reos'
            ? t('calendar.conflict.deletedInReos', { tool })
            : conflict.kept === 'tool'
              ? t('calendar.conflict.keptTool', { tool })
              : t('calendar.conflict.keptReos', { tool });

    return (
        <Alert className="border-reos-tone-info-fg/30 bg-reos-tone-info-bg text-reos-tone-info-fg">
            <History aria-hidden />
            <AlertTitle className="line-clamp-none">{title}</AlertTitle>
            <AlertDescription className="space-y-3 text-foreground">
                <p>
                    {losing.deleted
                        ? t('calendar.conflict.otherDeleted')
                        : t('calendar.conflict.other', {
                              title: losing.title ?? t('calendar.untitled'),
                              when: eventWhen(
                                  losing.starts_at,
                                  losing.all_day,
                                  i18n.language,
                                  timeZone,
                              ),
                          })}
                </p>
                <p className="text-muted-foreground">
                    {t('calendar.conflict.until', {
                        date: shortDate(conflict.until, i18n.language),
                    })}
                </p>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        router.post(
                            undo(conflict.id).url,
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('calendar.conflict.undo')}
                </Button>
            </AlertDescription>
        </Alert>
    );
}

/** Busy only, or a calendar REOS can't change: what it is and where it lives. */
function ReadOnly({
    event,
    timeZone,
}: {
    event: CalendarEvent;
    timeZone: string;
}) {
    const { t, i18n } = useTranslation();

    return (
        <>
            <DialogHeader>
                <DialogTitle>{eventTitle(event, t)}</DialogTitle>
                <DialogDescription>
                    {eventWhen(
                        event.starts_at,
                        event.all_day,
                        i18n.language,
                        timeZone,
                    )}
                    {' · '}
                    {event.calendar
                        ? t('calendar.inCalendar', {
                              calendar: event.calendar,
                              tool: sourceName(event, t),
                          })
                        : sourceName(event, t)}
                </DialogDescription>
            </DialogHeader>
            {event.location ? <p>{event.location}</p> : null}
            <p className="text-muted-foreground">
                {event.busy
                    ? t('calendar.readOnly.busy')
                    : t('calendar.readOnly.calendar')}
            </p>
            {event.link && event.tool ? (
                <DialogFooter>
                    <Button variant="outline" asChild>
                        <a href={event.link} target="_blank" rel="noreferrer">
                            <ExternalLink aria-hidden className="size-5" />
                            {t('calendar.openIn', { tool: event.tool })}
                        </a>
                    </Button>
                </DialogFooter>
            ) : null}
        </>
    );
}
