import { router } from 'expo-router';
import { Medal, Play, Share2, Swords } from 'lucide-react-native';
import { Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { ChallengeBoard } from '@/lib/practice';
import { Button, Card, Icon, Text } from '@/ui';

/**
 * One challenge (screen 52, A9.3b): who sent it, Share for your own, Accept
 * or carry on, and the board by points with first names only.
 */
export function ChallengeCard({ challenge, onAccept }: { challenge: ChallengeBoard; onAccept?: () => void }) {
  const { t, i18n } = useTranslation();
  const until = new Date(challenge.expires_at).toLocaleDateString(i18n.language, { month: 'long', day: 'numeric' });
  const open = (id: number, results: boolean) => router.push({ pathname: results ? '/practice/results/[id]' : '/practice/[id]', params: { id: String(id) } });

  return (
    <Card>
      <View style={styles.row}>
        <Icon icon={Swords} color="action" />
        <Text weight="bold" style={styles.flex}>
          {challenge.mine ? t('practice.challenges.yours') : t('practice.challenges.from', { name: challenge.from })}
        </Text>
      </View>
      <Text tone="muted">{challenge.expired ? t('practice.challenges.ended') : t('practice.challenges.until', { count: challenge.questions, date: until })}</Text>
      {challenge.attempt_id === null ? (
        onAccept && !challenge.expired ? <Button icon={Play} label={t('practice.challenges.accept')} onPress={onAccept} fullWidth /> : null
      ) : challenge.submitted ? (
        <Button variant="secondary" label={t('practice.seeResults')} onPress={() => open(challenge.attempt_id ?? 0, true)} />
      ) : (
        <Button icon={Play} label={t('practice.challenges.answer')} onPress={() => open(challenge.attempt_id ?? 0, false)} />
      )}
      {challenge.mine && !challenge.expired ? (
        <Button variant="secondary" icon={Share2} label={t('practice.challenges.share')} onPress={() => void Share.share({ message: t('practice.challenges.shareMessage', { link: challenge.link }) })} />
      ) : null}
      {challenge.entries.length > 0 ? (
        <View accessibilityLabel={t('practice.challenges.board')} style={styles.board}>
          {challenge.entries.map((entry, i) => (
            <View key={`${entry.first_name}-${i}`} style={styles.row}>
              {i === 0 && entry.finished ? <Icon icon={Medal} color="action" size={18} /> : null}
              <Text weight="semibold" style={styles.flex}>
                {entry.mine ? t('practice.challenges.you', { name: entry.first_name }) : entry.first_name}
              </Text>
              <Text tone="muted">{entry.finished ? t('practice.challenges.points', { count: entry.points, correct: entry.correct, total: entry.total }) : t('practice.challenges.playing')}</Text>
            </View>
          ))}
        </View>
      ) : null}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
  board: { gap: theme.space.xs },
}));
