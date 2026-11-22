import { router } from 'expo-router';
import { useMemo, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { scheduleSession, useSessions } from '@/lib/sessions';
import { Button, Callout, ChoiceRow, Eyebrow, OptionGroup, PillGroup, Screen, Text, TextField, TitleBar } from '@/ui';

type Kind = 'one_on_one' | 'live_class';

/** The next 14 days, as pills ("Today", "Tomorrow", "Wed, Nov 25"). */
function days(language: string, today: Date): { value: string; label: string }[] {
  return Array.from({ length: 14 }, (_, i) => {
    const d = new Date(today);
    d.setDate(d.getDate() + i);
    const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    const label = i === 0 ? 'today' : i === 1 ? 'tomorrow' : d.toLocaleDateString(language, { weekday: 'short', month: 'short', day: 'numeric' });

    return { value, label };
  });
}

/** Start times every 30 minutes from 7 AM to 9:30 PM. */
function times(language: string): { value: string; label: string }[] {
  return Array.from({ length: 30 }, (_, i) => {
    const h = 7 + Math.floor(i / 2);
    const m = i % 2 === 0 ? 0 : 30;
    const d = new Date(2026, 0, 1, h, m);

    return { value: `${String(h).padStart(2, '0')}:${m === 0 ? '00' : '30'}`, label: d.toLocaleTimeString(language, { hour: 'numeric', minute: '2-digit' }) };
  });
}

/**
 * Schedule a session on the phone (B6.3b; instructors and mentors): a 1-on-1
 * or a live class with people you work with, at a time in the phone's zone.
 */
export default function NewSessionScreen() {
  const { t, i18n } = useTranslation();
  const { list } = useSessions();
  const [today] = useState(() => new Date());
  const dayOptions = useMemo(
    () => days(i18n.language, today).map((d) => ({ ...d, label: d.label === 'today' ? t('sessions.schedule.today') : d.label === 'tomorrow' ? t('sessions.schedule.tomorrow') : d.label })),
    [i18n.language, today, t],
  );
  const timeOptions = useMemo(() => times(i18n.language), [i18n.language]);
  const [kind, setKind] = useState<Kind>('one_on_one');
  const [title, setTitle] = useState('');
  const [day, setDay] = useState(dayOptions[1]?.value ?? '');
  const [time, setTime] = useState('10:00');
  const [minutes, setMinutes] = useState('45');
  const [guests, setGuests] = useState<number[]>([]);
  const [agenda, setAgenda] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const choose = (id: number) =>
    setGuests(kind === 'one_on_one' ? (guests.includes(id) ? [] : [id]) : guests.includes(id) ? guests.filter((g) => g !== id) : [...guests, id]);

  const submit = async () => {
    setBusy(true);
    const result = await scheduleSession({
      kind,
      title: title.trim(),
      agenda: agenda.trim() === '' ? null : agenda,
      starts_at: new Date(`${day}T${time}:00`).toISOString(),
      minutes: minutes as Parameters<typeof scheduleSession>[0]['minutes'],
      guests,
    }).catch(() => ({ ok: false as const, message: '' }));
    setBusy(false);

    if (result.ok) {
      router.replace({ pathname: '/sessions/[id]', params: { id: result.data.id } });
    } else {
      setError(result.message || t('consents.errors.generic'));
    }
  };

  return (
    <Screen
      footer={<Button label={t('sessions.schedule.submit')} loading={busy} disabled={guests.length === 0 || title.trim() === ''} fullWidth onPress={() => void submit()} />}
    >
      <TitleBar title={t('sessions.schedule.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('sessions.schedule.description')}</Text>
      {error ? <Callout tone="danger" title={error} live /> : null}
      <OptionGroup<Kind>
        label={t('sessions.schedule.kind')}
        value={kind}
        onChange={(next) => {
          setKind(next);
          setGuests(guests.slice(0, 1));
        }}
        options={[
          { value: 'one_on_one', label: t('sessions.kinds.one_on_one'), description: t('sessions.schedule.oneOnOne') },
          { value: 'live_class', label: t('sessions.kinds.live_class'), description: t('sessions.schedule.liveClass') },
        ]}
      />
      <TextField label={t('sessions.schedule.name')} value={title} onChangeText={setTitle} placeholder={t('sessions.schedule.namePlaceholder')} maxLength={120} />
      <Eyebrow>{t('sessions.schedule.date')}</Eyebrow>
      <PillGroup label={t('sessions.schedule.date')} value={day} options={dayOptions} onChange={setDay} />
      <Eyebrow>{t('sessions.schedule.time')}</Eyebrow>
      <PillGroup label={t('sessions.schedule.time')} value={time} options={timeOptions} onChange={setTime} />
      <Eyebrow>{t('sessions.schedule.length')}</Eyebrow>
      <PillGroup
        label={t('sessions.schedule.length')}
        value={minutes}
        options={(list?.lengths ?? [30, 45, 60]).map((m) => ({ value: String(m), label: t('sessions.minutes', { count: m }) }))}
        onChange={setMinutes}
      />
      <Eyebrow>{kind === 'one_on_one' ? t('sessions.schedule.who') : t('sessions.schedule.whoMany')}</Eyebrow>
      <View style={styles.list}>
        {list !== null && list.contacts.length === 0 ? <Text tone="muted">{t('sessions.schedule.noContacts')}</Text> : null}
        {(list?.contacts ?? []).map((contact) => (
          <ChoiceRow key={contact.id} title={contact.name} detail={contact.organisation} selected={guests.includes(contact.id)} onPress={() => choose(contact.id)} />
        ))}
      </View>
      <TextField label={t('sessions.schedule.agenda')} value={agenda} onChangeText={setAgenda} multiline />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.sm },
}));
