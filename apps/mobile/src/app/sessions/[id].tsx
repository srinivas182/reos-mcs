import { router, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { SessionRoom } from '@/features/sessions/SessionRoom';
import { sessionTime } from '@/features/sessions/SessionRow';
import { useAuth } from '@/lib/auth';
import { endSession, joinSession, type RoomConnection, saveNotes, useSession } from '@/lib/sessions';
import { Button, Callout, Eyebrow, Screen, Text, TextField, TitleBar } from '@/ui';

/**
 * A session on the phone (B6.3): when and with whom, Join from five
 * minutes before, the room (screen 15), and the shared notes (screen 16).
 */
export default function SessionScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const me = user?.id ?? 0;
  const [connection, setConnection] = useState<RoomConnection | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [draft, setDraft] = useState<string | null>(null);
  const [notesError, setNotesError] = useState<string | null>(null);
  const leave = useCallback(() => setConnection(null), []);
  const { session, failed, reload, setSession } = useSession(id, user?.id ?? null, leave);

  if (failed) {
    return (
      <Screen>
        <TitleBar title={t('sessions.title')} onBack={() => router.back()} />
        <Callout tone="danger" title={t('sessions.errors.over')} />
      </Screen>
    );
  }

  if (session === null) {
    return (
      <Screen>
        <TitleBar title={t('sessions.title')} onBack={() => router.back()} />
      </Screen>
    );
  }

  const others = session.people.filter((p) => p.id !== me).map((p) => p.name);
  const opens = new Date(session.join_opens_at).toLocaleTimeString(i18n.language, { hour: 'numeric', minute: '2-digit' });
  const over = session.status === 'ended' || session.status === 'cancelled';
  const notes = draft ?? session.notes ?? '';

  const join = async () => {
    setBusy(true);
    const result = await joinSession(session.id).catch(() => ({ ok: false as const, message: '' }));
    setBusy(false);

    if (result.ok) {
      setError(null);
      setConnection(result.data);
      reload();
    } else {
      setError(result.message || t('sessions.room.failed'));
    }
  };

  const end = async () => {
    setConnection(null);
    const ended = await endSession(session.id).catch(() => null);
    if (ended) setSession(ended);
  };

  const save = async () => {
    const result = await saveNotes(session.id, notes).catch(() => ({ ok: false as const, message: '' }));

    if (result.ok) {
      setSession(result.data);
      setDraft(null);
      setNotesError(null);
    } else {
      setNotesError(result.message || t('consents.errors.generic'));
    }
  };

  return (
    <Screen>
      <TitleBar title={session.title} onBack={() => router.back()} />
      <Text tone="muted">
        {sessionTime(session, i18n.language)} ·{' '}
        {session.kind === 'live_class' ? t('sessions.people', { count: others.length }) : t('sessions.with', { names: others.join(', ') })}
      </Text>

      {connection ? (
        <SessionRoom session={session} connection={connection} me={me} onLeave={leave} onEnd={() => void end()} />
      ) : over ? (
        <Callout tone="neutral" title={session.status === 'cancelled' ? t('sessions.wasCancelled') : t('sessions.room.ended')} />
      ) : (
        <View style={styles.block}>
          {session.agenda ? (
            <>
              <Eyebrow>{t('sessions.agenda')}</Eyebrow>
              <Text>{session.agenda}</Text>
            </>
          ) : null}
          <Text tone="muted">{session.can_join ? t('sessions.ready') : t('sessions.opensNote', { time: opens })}</Text>
          <Button
            label={session.can_join ? t('sessions.join') : t('sessions.opensAt', { time: opens })}
            disabled={!session.can_join}
            loading={busy}
            fullWidth
            onPress={() => void join()}
          />
          {error ? <Callout tone="danger" title={error} live /> : null}
        </View>
      )}

      {session.status !== 'cancelled' ? (
        <View style={styles.block}>
          <Eyebrow>{t('sessions.notes.title')}</Eyebrow>
          <Text tone="muted">{t('sessions.notes.description')}</Text>
          <TextField label={t('sessions.notes.title')} value={notes} onChangeText={setDraft} multiline placeholder={t('sessions.notes.placeholder')} />
          {session.notes_updated_by && session.notes_updated_at ? (
            <Text variant="caption" tone="muted">
              {t('sessions.notes.updated', {
                name: session.notes_updated_by,
                when: new Date(session.notes_updated_at).toLocaleString(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }),
              })}
            </Text>
          ) : null}
          {notesError ? <Callout tone="danger" title={notesError} live /> : null}
          <Button variant="secondary" label={t('sessions.notes.save')} disabled={draft === null} onPress={() => void save()} />
        </View>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.md },
}));
