import { LogOut, Share2, Swords, Users } from 'lucide-react-native';
import { Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { StudyGroup } from '@/lib/practice';
import { Button, Card, Icon, Text } from '@/ui';

/**
 * A study group (screen 52, A9.3c): its size, this week's leaderboard of
 * questions answered and accuracy (first names only), Share the invite,
 * Challenge the group and Leave; Join for one not joined yet.
 */
export function GroupCard({ group, onJoin, onChallenge, onLeave }: { group: StudyGroup; onJoin?: () => void; onChallenge?: () => void; onLeave?: () => void }) {
  const { t } = useTranslation();

  return (
    <Card>
      <View style={styles.row}>
        <Icon icon={Users} color="action" />
        <Text weight="bold" style={styles.flex}>
          {group.name}
        </Text>
      </View>
      <Text tone="muted">
        {group.school ? `${t('practice.groups.school', { school: group.school })} · ` : ''}
        {t('practice.groups.members', { count: group.members, max: group.max })}
      </Text>
      {group.leaderboard.length > 0 ? (
        <View accessibilityLabel={t('practice.groups.leaderboard')} style={styles.board}>
          {group.leaderboard.map((row, i) => (
            <View key={`${row.first_name}-${i}`} style={styles.row}>
              <Text weight="semibold" style={styles.flex}>
                {`${i + 1}. ${row.mine ? t('practice.groups.you', { name: row.first_name }) : row.first_name}`}
              </Text>
              <Text tone="muted">
                {row.accuracy === null ? t('practice.groups.noAnswers') : `${t('practice.groups.questions', { count: row.questions })} · ${t('practice.groups.accuracy', { percent: row.accuracy })}`}
              </Text>
            </View>
          ))}
        </View>
      ) : null}
      {!group.mine && onJoin ? <Button label={t('practice.groups.join')} onPress={onJoin} fullWidth /> : null}
      {group.mine && onChallenge ? <Button icon={Swords} label={t('practice.groups.challenge')} onPress={onChallenge} /> : null}
      {group.mine ? (
        <Button variant="secondary" icon={Share2} label={t('practice.groups.share')} onPress={() => void Share.share({ message: t('practice.groups.shareMessage', { name: group.name, link: group.link }) })} />
      ) : null}
      {group.mine && onLeave ? <Button variant="ghost" icon={LogOut} label={t('practice.groups.leave')} onPress={onLeave} /> : null}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  flex: { flex: 1 },
  board: { gap: theme.space.xs },
}));
