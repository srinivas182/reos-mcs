/**
 * The session room on the phone (instructor and mentor journey screen 15):
 * everyone's video, a timer with a two-minute warning, Leave, and End for
 * everyone for the host. At the booked end the room closes for everyone.
 * Where LiveKit isn't set up (outside production), people show as name
 * tiles and everything else works the same.
 */
import { AudioSession, LiveKitRoom, isTrackReference, useTracks, VideoTrack } from '@livekit/react-native';
import { Track } from 'livekit-client';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { clock, type RoomConnection, type Session } from '@/lib/sessions';
import { Button, Callout, initialsOf, Text } from '@/ui';

function useSecondsLeft(until: string): number {
  const target = new Date(until).getTime();
  const [left, setLeft] = useState(() => Math.max(0, Math.round((target - Date.now()) / 1000)));

  useEffect(() => {
    const timer = setInterval(() => setLeft(Math.max(0, Math.round((target - Date.now()) / 1000))), 1000);

    return () => clearInterval(timer);
  }, [target]);

  return left;
}

/** Cameras in the room (and a shared screen). */
function Stage() {
  const tracks = useTracks([Track.Source.Camera, Track.Source.ScreenShare]);

  return (
    <View style={styles.grid}>
      {tracks.map((track) =>
        isTrackReference(track) ? (
          <VideoTrack key={`${track.participant.identity}-${track.source}`} trackRef={track} style={styles.video} objectFit="cover" />
        ) : null,
      )}
    </View>
  );
}

function NameTiles({ session, me }: { session: Session; me: number }) {
  const { t } = useTranslation();

  return (
    <View style={styles.grid} accessibilityLabel={t('sessions.room.people')}>
      {session.people.slice(0, 7).map((person) => (
        <View key={person.id} style={styles.tile}>
          <View style={styles.initials}>
            <Text variant="title" weight="bold">
              {initialsOf(person.name)}
            </Text>
          </View>
          <Text style={styles.tileName} weight="medium">
            {person.id === me ? t('sessions.room.you', { name: person.name }) : person.name}
            {person.role === 'host' ? ` · ${t('sessions.host')}` : ''}
          </Text>
        </View>
      ))}
    </View>
  );
}

export function SessionRoom({
  session,
  connection,
  me,
  onLeave,
  onEnd,
}: {
  session: Session;
  connection: RoomConnection;
  me: number;
  onLeave: () => void;
  onEnd: () => void;
}) {
  const { t, i18n } = useTranslation();
  const left = useSecondsLeft(connection.ends_at);
  const closesAt = new Date(connection.ends_at).toLocaleTimeString(i18n.language, { hour: 'numeric', minute: '2-digit' });
  const live = !connection.simulated && connection.url !== null && connection.token !== null;

  useEffect(() => {
    if (left === 0) onLeave();
  }, [left, onLeave]);

  // Calls need the phone's audio session (speaker, Bluetooth) while in the room.
  useEffect(() => {
    if (!live) return undefined;
    void AudioSession.startAudioSession();

    return () => {
      void AudioSession.stopAudioSession();
    };
  }, [live]);

  return (
    <View style={styles.room}>
      <View style={styles.bar}>
        <Text weight="bold" accessibilityRole="timer" accessibilityLabel={t('sessions.room.timeLeft', { time: clock(left) })}>
          {clock(left)}
        </Text>
        <Text tone="muted">{t('sessions.room.left', { time: closesAt })}</Text>
      </View>
      {left > 0 && left <= 120 ? <Callout tone="warning" title={t('sessions.room.twoMinutes', { time: closesAt })} live /> : null}
      {live ? (
        <LiveKitRoom serverUrl={connection.url ?? undefined} token={connection.token ?? undefined} connect audio video onDisconnected={onLeave}>
          <Stage />
        </LiveKitRoom>
      ) : (
        <>
          <Text tone="muted">{t('sessions.room.simulated')}</Text>
          <NameTiles session={session} me={me} />
        </>
      )}
      <View style={styles.actions}>
        <Button variant="secondary" label={t('sessions.room.leave')} onPress={onLeave} />
        {session.me === 'host' ? <Button variant="destructive" label={t('sessions.room.end')} onPress={onEnd} /> : null}
      </View>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  room: { gap: theme.space.md },
  bar: { flexDirection: 'row', alignItems: 'baseline', gap: theme.space.sm, flexWrap: 'wrap' },
  grid: { gap: theme.space.sm },
  video: { width: '100%', aspectRatio: 4 / 3, borderRadius: theme.radius.lg, overflow: 'hidden', backgroundColor: theme.colors.heroBg },
  tile: { aspectRatio: 16 / 9, borderRadius: theme.radius.lg, backgroundColor: theme.colors.heroBg, alignItems: 'center', justifyContent: 'center', gap: theme.space.sm },
  initials: { width: 72, height: 72, borderRadius: 36, backgroundColor: theme.colors.surface, alignItems: 'center', justifyContent: 'center' },
  tileName: { color: theme.colors.heroText },
  actions: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap' },
}));
