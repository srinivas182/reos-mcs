import type { LucideIcon } from 'lucide-react-native';
import { BookOpen, CalendarDays, CalendarPlus, Home, Lock, SquareCheck, TriangleAlert, Users, Video } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { AppointmentSheet } from '@/features/calendar/AppointmentSheet';
import { api } from '@/lib/api';
import { type CalendarEvent, type CalendarKind, eventTime, eventTitle, sourceName, useCalendarDay } from '@/lib/calendar';
import { Button, Callout, Card, Chip, ListRow, SectionTitle, Text, useUndoToast } from '@/ui';
import type { Tone } from '@/ui';

const ICONS: Record<CalendarKind, LucideIcon> = {
  appointment: Users,
  showing: Home,
  deadline: TriangleAlert,
  session: Video,
  ce: BookOpen,
  todo: SquareCheck,
  event: CalendarDays,
};

const TONES: Partial<Record<CalendarKind, Tone>> = { deadline: 'danger', showing: 'success', ce: 'warning' };

/**
 * Today's calendar on the phone's Today (agent app journey, screen 5; B4.1):
 * each event with its time and the tool it came from, New appointment, and
 * any event opens in a sheet. A small chip marks one changed in two places
 * or not yet saved to the tool.
 */
export function TodayAgenda() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { today, reload } = useCalendarDay();
  const [open, setOpen] = useState<CalendarEvent | 'new' | null>(null);

  if (today === null) {
    return null;
  }

  const bringBack = async (conflict: number) => {
    const { response } = await api().POST('/calendar/conflicts/{conflict}/undo', { params: { path: { conflict } } });

    if (response.ok) {
      toast({ message: t('calendar.conflict.undone') });
      void reload();
    }
  };

  return (
    <View style={styles.section}>
      <SectionTitle>{t('calendar.today')}</SectionTitle>
      {today.removed.map((removed) => (
        <Callout
          key={removed.conflict}
          tone="warning"
          title={t('calendar.removed.title')}
          body={t('calendar.removed.body', {
            title: removed.title ?? t('calendar.untitled'),
            date: removed.at ? new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric' }).format(Date.parse(removed.at) + 30 * 86_400_000) : '',
          })}
          action={<Button label={t('calendar.removed.bringBack')} variant="secondary" onPress={() => void bringBack(removed.conflict)} />}
        />
      ))}
      {today.data.length === 0 ? (
        <Card>
          <Text weight="semibold">{t('calendar.empty')}</Text>
          <Text tone="muted">{t('calendar.emptyBody')}</Text>
        </Card>
      ) : (
        today.data.map((event) => (
          <ListRow
            key={event.id}
            icon={event.busy ? Lock : ICONS[event.kind]}
            tone={event.busy ? 'neutral' : (TONES[event.kind] ?? 'info')}
            title={eventTitle(event, t)}
            subtitle={`${eventTime(event, t, i18n.language)} · ${sourceName(event, t)}`}
            trailing={
              event.conflict ? (
                <Chip tone="info" label={t('calendar.conflict.short')} />
              ) : event.sync === 'failed' ? (
                <Chip tone="danger" label={t('calendar.sync.short')} />
              ) : undefined
            }
            onPress={() => setOpen(event)}
          />
        ))
      )}
      <Button label={t('calendar.newAppointment')} icon={CalendarPlus} variant="secondary" fullWidth onPress={() => setOpen('new')} />
      <AppointmentSheet event={open} day={new Date()} writeTo={today.writeTo} onClose={() => setOpen(null)} onChanged={() => void reload()} />
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  section: { gap: theme.space.sm },
}));
