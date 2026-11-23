import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type PlayerState, spent } from '@/lib/player';
import { Card, ProgressBar, Text } from '@/ui';

/**
 * How much of the lesson's required time has counted (A8.2), and why the
 * last stretch didn't when it didn't. Offline it says the time is kept on
 * the phone until it's sent.
 */
export function SeatMeter({ state, offline }: { state: PlayerState; offline: boolean }) {
  const { t } = useTranslation();
  // The phone's own words for "not in front".
  const reason = state.reason === 'hidden' ? ('player.reasons.hiddenApp' as const) : state.reason ? (`player.reasons.${state.reason}` as const) : null;

  return (
    <Card>
      <View style={styles.row}>
        <Text weight="semibold" style={styles.flex}>
          {state.completed ? t('player.meter.done') : t('player.meter.counted', { counted: spent(state.counted), required: spent(state.required) })}
        </Text>
        <Text tone="muted">{`${state.percent}%`}</Text>
      </View>
      <ProgressBar value={state.percent} label={t('player.meter.progress')} color={state.completed ? 'success' : 'action'} />
      {!state.completed && reason ? <Text tone="muted">{t(reason)}</Text> : null}
      {offline ? <Text tone="muted">{t('player.offline.recording')}</Text> : null}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
