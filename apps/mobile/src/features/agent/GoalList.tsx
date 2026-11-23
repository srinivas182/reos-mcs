import { Check, Lock } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { formatDay } from '@/lib/licences';
import { GOALS, type Goal, type ModeState } from '@/lib/agent';
import { Chip, Icon, Text } from '@/ui';

/** The four goals (screens 5 and 146); Own stays locked until it opens. */
export function GoalList({ mode, value, onChange }: { mode: ModeState; value: Goal | null; onChange: (goal: Goal) => void }) {
  const { t, i18n } = useTranslation();

  return (
    <View accessibilityRole="radiogroup" accessibilityLabel={t('agents.modes.title')} style={styles.list}>
      {GOALS.map((goal) => {
        const locked = goal === 'own' && !mode.own.unlocked;
        const on = value === goal;

        return (
          <Pressable
            key={goal}
            accessibilityRole="radio"
            accessibilityState={{ checked: on, disabled: locked }}
            disabled={locked}
            onPress={() => onChange(goal)}
            style={[styles.card, on && styles.on, locked && styles.locked]}
          >
            <View style={styles.head}>
              <Text variant="bodyLarge" weight="semibold" style={styles.flex}>
                {t(`agents.modes.goals.${goal}.goal`)}
              </Text>
              {on ? <Icon icon={Check} color="action" /> : null}
              {locked ? <Icon icon={Lock} color="textMuted" /> : null}
            </View>
            <Text tone="muted">{t(`agents.modes.goals.${goal}.name`)} · {t(`agents.modes.goals.${goal}.body`)}</Text>
            {locked ? (
              <Text>
                {mode.own.unlocks_on
                  ? t('agents.modes.ownLocked', { date: formatDay(mode.own.unlocks_on, i18n.language) ?? '', years: mode.own.years })
                  : t('agents.modes.ownLockedNoDate', { years: mode.own.years })}
              </Text>
            ) : null}
            {mode.needs_setup && goal === mode.suggested_default ? <Chip label={t('agents.modes.suggestedDefault')} tone="info" /> : null}
          </Pressable>
        );
      })}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.sm },
  card: { gap: theme.space.xs, padding: theme.space.md, borderRadius: theme.m.radius.card, borderWidth: 2, borderColor: theme.colors.border, backgroundColor: theme.colors.surface, minHeight: 48 },
  on: { borderColor: theme.colors.action },
  locked: { opacity: 0.7 },
  head: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
