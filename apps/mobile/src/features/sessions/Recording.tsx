/**
 * Recording on the phone (B6.3c): the question to everyone in the room (it
 * starts only when all agree; one "no" cancels), the "Recording" sign with
 * Stop for anyone, and finished recordings to watch until they're deleted.
 */
import { useVideoPlayer, VideoView } from 'expo-video';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { answerRecording, deleteRecording, type Recording, type Session, stopRecording } from '@/lib/sessions';
import { Button, Callout, Card, Eyebrow, Text } from '@/ui';

export function RecordingBanner({ session, recording, onChanged }: { session: Session; recording: Recording; onChanged: () => void }) {
  const { t } = useTranslation();
  const answer = (agreed: boolean) => void answerRecording(session.id, recording.id, agreed).finally(onChanged);
  const stop = () => void stopRecording(session.id, recording.id).finally(onChanged);

  if (recording.status === 'recording' || recording.status === 'stopping') {
    return (
      <Callout
        tone="danger"
        title={recording.status === 'stopping' ? t('sessions.recording.stopping') : t('sessions.recording.recording')}
        live
        action={recording.status === 'recording' ? <Button variant="secondary" label={t('sessions.recording.stop')} onPress={stop} /> : undefined}
      />
    );
  }

  return (
    <Callout
      tone="warning"
      title={t('sessions.recording.asking', { name: recording.requested_by ?? '' })}
      body={[t('sessions.recording.askBody'), recording.waiting.length > 0 ? t('sessions.recording.waiting', { names: recording.waiting.join(', ') }) : null].filter(Boolean).join('\n')}
      live
      action={
        recording.my_answer === null ? (
          <View style={styles.actions}>
            <Button label={t('sessions.recording.agree')} onPress={() => answer(true)} />
            <Button variant="secondary" label={t('sessions.recording.decline')} onPress={() => answer(false)} />
          </View>
        ) : (
          <Button variant="secondary" label={t('sessions.recording.decline')} onPress={stop} />
        )
      }
    />
  );
}

function Player({ url }: { url: string }) {
  const { t } = useTranslation();
  const player = useVideoPlayer(url);

  return <VideoView player={player} style={styles.video} nativeControls accessibilityLabel={t('sessions.recording.watch')} />;
}

export function RecordingsList({ session, onChanged }: { session: Session; onChanged: () => void }) {
  const { t, i18n } = useTranslation();
  const done = session.recordings.filter((r) => r.status === 'ready' || r.status === 'failed');

  if (done.length === 0) return null;

  return (
    <View style={styles.block}>
      <Eyebrow>{t('sessions.recording.title')}</Eyebrow>
      {done.map((recording) => (
        <Card key={recording.id}>
          <View style={styles.block}>
            {recording.status === 'failed' ? (
              <Text>{t('sessions.recording.failed')}</Text>
            ) : recording.play_url ? (
              <Player url={recording.play_url} />
            ) : (
              <Text tone="muted">{t('sessions.recording.simulated')}</Text>
            )}
            <Text variant="caption" tone="muted">
              {[
                recording.duration_seconds ? t('sessions.recording.duration', { minutes: Math.max(1, Math.round(recording.duration_seconds / 60)) }) : null,
                recording.expires_at
                  ? t('sessions.recording.expires', { date: new Date(recording.expires_at).toLocaleDateString(i18n.language, { dateStyle: 'medium' }) })
                  : null,
              ]
                .filter(Boolean)
                .join(' · ')}
            </Text>
            <Button variant="secondary" label={t('sessions.recording.delete')} onPress={() => void deleteRecording(session.id, recording.id).finally(onChanged)} />
          </View>
        </Card>
      ))}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.md },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  video: { width: '100%', aspectRatio: 16 / 9, borderRadius: theme.radius.md, backgroundColor: theme.colors.heroBg },
}));
