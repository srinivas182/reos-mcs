import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import {
  type AppointmentForm,
  type CalendarEvent,
  DURATIONS,
  KINDS,
  addDays,
  blankForm,
  dayLabel,
  eventTitle,
  eventWhen,
  firstError,
  formFromEvent,
  payload,
  sourceName,
} from '@/lib/calendar';
import { Button, Callout, IconButton, PillGroup, Sheet, SwitchRow, Text, TextField, useUndoToast } from '@/ui';

type Props = {
  /** The event, `'new'`, or null when closed. */
  event: CalendarEvent | 'new' | null;
  /** The day a new appointment starts on. */
  day: Date;
  writeTo: { tool: string; calendar: string } | null;
  onClose: () => void;
  /** After any change, so the agenda reloads. */
  onChanged: () => void;
};

/**
 * "New appointment" and any event opened from Today (agent app journey;
 * B4.1), as a sheet.
 *
 * - Date and time move in steps (a day, 15 minutes) with 48-point buttons;
 *   how long is a choice of common lengths. All-day events take days.
 * - Guests are only emailed when the person turns it on.
 * - Changed in REOS and in the tool: what was kept, the other version, and
 *   Undo. Not yet saved to the tool: Try again.
 * - Deleting (REOS's own events only) offers Undo instead of asking first.
 * - Busy only, or a calendar REOS can't change: details and Open in the tool.
 */
export function AppointmentSheet({ event, day, writeTo, onClose, onChanged }: Props) {
  const { t } = useTranslation();

  if (event === null) {
    return null;
  }

  const title = event === 'new' ? t('calendar.newAppointment') : eventTitle(event, t);

  return (
    <Sheet open onClose={onClose} title={title}>
      {event !== 'new' && !event.editable ? (
        <ReadOnly event={event} />
      ) : (
        <Editor key={event === 'new' ? 'new' : event.id} event={event} day={day} writeTo={writeTo} onClose={onClose} onChanged={onChanged} />
      )}
    </Sheet>
  );
}

function Editor({ event, day, writeTo, onClose, onChanged }: Omit<Props, 'event'> & { event: CalendarEvent | 'new' }) {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const isNew = event === 'new';
  const [form, setForm] = useState<AppointmentForm>(() => (isNew ? blankForm(day) : formFromEvent(event)));
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const set = <K extends keyof AppointmentForm>(key: K, value: AppointmentForm[K]) => setForm((current) => ({ ...current, [key]: value }));
  const madeInReos = isNew || event.source === 'reos';
  const time = new Intl.DateTimeFormat(i18n.language, { hour: 'numeric', minute: '2-digit' }).format(form.start);
  const moveMinutes = (minutes: number) => set('start', new Date(form.start.getTime() + minutes * 60_000));

  const save = async () => {
    setBusy(true);
    setProblem(null);
    const body = payload(form, madeInReos);
    const { response, error } = isNew
      ? await api().POST('/calendar/events', { body })
      : await api().PUT('/calendar/events/{event}', { params: { path: { event: event.id } }, body });
    setBusy(false);

    if (!response.ok) {
      setProblem(firstError(error) ?? t('common.tryAgain'));

      return;
    }

    toast({ message: isNew ? (writeTo ? t('calendar.saved', { title: form.title.trim() }) : t('calendar.savedLocal')) : t('calendar.updated') });
    onChanged();
    onClose();
  };

  const remove = async () => {
    if (isNew) return;

    const { response } = await api().DELETE('/calendar/events/{event}', { params: { path: { event: event.id } } });

    if (response.ok) {
      onChanged();
      onClose();
      toast({
        message: t('calendar.deleted', { title: eventTitle(event, t) }),
        onUndo: async () => {
          await api().POST('/calendar/events/{event}/restore', { params: { path: { event: event.id } } });
          onChanged();
        },
      });
    }
  };

  const retry = async () => {
    if (isNew) return;

    await api().POST('/calendar/events/{event}/retry', { params: { path: { event: event.id } } });
    toast({ message: t('calendar.retrying') });
    onChanged();
  };

  return (
    <>
      <Text tone="muted">
        {isNew
          ? writeTo
            ? t('calendar.writeTo', { calendar: writeTo.calendar, tool: writeTo.tool })
            : t('calendar.writeToNone')
          : event.tool && event.calendar
            ? t('calendar.inCalendar', { calendar: event.calendar, tool: event.tool })
            : t('calendar.sync.local')}
      </Text>

      {!isNew && event.conflict ? <ConflictNotice event={event} onChanged={onChanged} onClose={onClose} /> : null}
      {!isNew && event.sync === 'failed' ? (
        <Callout tone="danger" title={t('calendar.sync.failed', { tool: event.tool ?? '' })} action={<Button label={t('calendar.tryAgain')} variant="secondary" onPress={() => void retry()} />} />
      ) : null}

      <TextField label={t('calendar.fields.title')} value={form.title} placeholder={t('calendar.fields.titlePlaceholder')} onChangeText={(value) => set('title', value)} />

      {madeInReos ? (
        <PillGroup
          label={t('calendar.fields.kind')}
          value={form.kind}
          options={KINDS.map((kind) => ({ value: kind, label: t(`calendar.kinds.${kind}`) }))}
          onChange={(value) => set('kind', value)}
        />
      ) : null}

      <SwitchRow label={t('calendar.allDay')} value={form.allDay} onValueChange={(value) => set('allDay', value)} />

      <Stepper
        label={t('calendar.fields.date')}
        value={dayLabel(form.start, i18n.language)}
        earlier={t('calendar.step.earlierDay')}
        later={t('calendar.step.laterDay')}
        onEarlier={() => set('start', addDays(form.start, -1))}
        onLater={() => set('start', addDays(form.start, 1))}
      />

      {form.allDay ? (
        <Stepper
          label={t('calendar.fields.endDate')}
          value={dayLabel(addDays(form.start, form.days - 1), i18n.language)}
          earlier={t('calendar.step.earlierDay')}
          later={t('calendar.step.laterDay')}
          onEarlier={() => set('days', Math.max(1, form.days - 1))}
          onLater={() => set('days', Math.min(14, form.days + 1))}
        />
      ) : (
        <>
          <Stepper
            label={t('calendar.fields.start')}
            value={time}
            earlier={t('calendar.step.earlierTime')}
            later={t('calendar.step.laterTime')}
            onEarlier={() => moveMinutes(-15)}
            onLater={() => moveMinutes(15)}
          />
          <PillGroup
            label={t('calendar.fields.duration')}
            value={String(form.minutes)}
            options={DURATIONS.map((minutes) => ({ value: String(minutes), label: t(`calendar.durations.${minutes}`) }))}
            onChange={(value) => set('minutes', Number(value))}
          />
        </>
      )}

      <TextField label={t('calendar.fields.location')} value={form.location} onChangeText={(value) => set('location', value)} />
      <TextField
        label={t('calendar.fields.guests')}
        hint={t('calendar.fields.guestsHint')}
        value={form.guests}
        keyboardType="email-address"
        autoCapitalize="none"
        onChangeText={(value) => set('guests', value)}
      />
      {form.guests.trim() !== '' ? (
        <SwitchRow label={t('calendar.fields.notifyGuests')} description={t('calendar.fields.notifyHint')} value={form.notify} onValueChange={(value) => set('notify', value)} />
      ) : null}
      <TextField label={t('calendar.fields.notes')} value={form.notes} multiline onChangeText={(value) => set('notes', value)} />

      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Button label={isNew ? t('calendar.add') : t('calendar.save')} loading={busy} fullWidth onPress={() => void save()} />
      {!isNew && event.deletable ? <Button label={t('calendar.delete')} variant="ghost" fullWidth onPress={() => void remove()} /> : null}
      {!isNew && !event.deletable && event.tool ? (
        <Text variant="caption" tone="muted">
          {t('calendar.readOnly.notOurs', { tool: event.tool })}
        </Text>
      ) : null}
      {!isNew && event.link && event.tool ? (
        <Button label={t('calendar.openIn', { tool: event.tool })} variant="secondary" fullWidth onPress={() => void Linking.openURL(event.link ?? '')} />
      ) : null}
    </>
  );
}

/** A value with earlier and later buttons (date by day, time by 15 minutes). */
function Stepper({ label, value, earlier, later, onEarlier, onLater }: { label: string; value: string; earlier: string; later: string; onEarlier: () => void; onLater: () => void }) {
  return (
    <View style={styles.stepper}>
      <Text weight="semibold">{label}</Text>
      <View style={styles.stepperRow}>
        <IconButton icon={ChevronLeft} label={earlier} onPress={onEarlier} />
        <Text variant="bodyLarge" style={styles.stepperValue} accessibilityLabel={`${label}: ${value}`} accessibilityLiveRegion="polite">
          {value}
        </Text>
        <IconButton icon={ChevronRight} label={later} onPress={onLater} />
      </View>
    </View>
  );
}

function ConflictNotice({ event, onChanged, onClose }: { event: CalendarEvent; onChanged: () => void; onClose: () => void }) {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const conflict = event.conflict;

  if (!conflict) return null;

  const tool = event.tool ?? t('calendar.reos');
  const title =
    conflict.reason === 'deleted_in_reos'
      ? t('calendar.conflict.deletedInReos', { tool })
      : conflict.kept === 'tool'
        ? t('calendar.conflict.keptTool', { tool })
        : t('calendar.conflict.keptReos', { tool });
  const other = conflict.losing.deleted
    ? t('calendar.conflict.otherDeleted')
    : t('calendar.conflict.other', {
        title: conflict.losing.title ?? t('calendar.untitled'),
        when: eventWhen(conflict.losing.starts_at, conflict.losing.all_day, i18n.language),
      });
  const until = t('calendar.conflict.until', {
    date: new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric' }).format(new Date(conflict.until)),
  });

  const chooseOther = async () => {
    const { response } = await api().POST('/calendar/conflicts/{conflict}/undo', { params: { path: { conflict: conflict.id } } });

    if (response.ok) {
      toast({ message: t('calendar.conflict.undone') });
      onChanged();
      onClose();
    }
  };

  return <Callout tone="info" title={title} body={`${other} ${until}`} action={<Button label={t('calendar.conflict.undo')} variant="secondary" onPress={() => void chooseOther()} />} />;
}

function ReadOnly({ event }: { event: CalendarEvent }) {
  const { t, i18n } = useTranslation();

  return (
    <>
      <Text variant="bodyLarge">{eventWhen(event.starts_at, event.all_day, i18n.language)}</Text>
      <Text tone="muted">{event.calendar ? t('calendar.inCalendar', { calendar: event.calendar, tool: sourceName(event, t) }) : sourceName(event, t)}</Text>
      {event.location ? <Text>{event.location}</Text> : null}
      <Callout tone="neutral" title={event.busy ? t('calendar.readOnly.busy') : t('calendar.readOnly.calendar')} />
      {event.link && event.tool ? (
        <Button label={t('calendar.openIn', { tool: event.tool })} variant="secondary" fullWidth onPress={() => void Linking.openURL(event.link ?? '')} />
      ) : null}
    </>
  );
}

const styles = StyleSheet.create((theme) => ({
  stepper: { gap: theme.space.xs },
  stepperRow: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  stepperValue: { flex: 1, textAlign: 'center' },
}));
