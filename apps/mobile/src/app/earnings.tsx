import { router } from 'expo-router';
import { Wallet } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type EarningGroup, groupLines, money, useEarnings } from '@/lib/earnings';
import { Card, Chip, EmptyState, HeroCard, Icon, Screen, Sheet, Text, TitleBar } from '@/ui';

/**
 * Earnings (instructor screen 20, mentor screen 28; B5.2b): net this month
 * and the next payout, this month's shares grouped by product (tap one for
 * each sale), and the reminder that bank and tax details are managed on
 * Stripe from the web.
 */
export default function EarningsScreen() {
  const { t, i18n } = useTranslation();
  const { earnings, failed } = useEarnings();
  const [open, setOpen] = useState<EarningGroup | null>(null);
  const month = earnings?.month ?? '';
  const monthName = month ? new Intl.DateTimeFormat(i18n.language, { month: 'long', timeZone: 'UTC' }).format(new Date(`${month}-01T00:00:00Z`)) : '';
  const nextDay = earnings ? new Intl.DateTimeFormat(i18n.language, { weekday: 'short', timeZone: 'UTC' }).format(new Date(`${earnings.next_payout}T00:00:00Z`)) : '';
  const groups = earnings ? groupLines(earnings.lines, month) : [];
  const ready = earnings?.account?.state === 'ready';

  return (
    <Screen>
      <TitleBar title={t('mobile.earnings.title')} onBack={() => router.back()} action={monthName ? <Chip label={monthName} tone="neutral" /> : undefined} />

      {failed && !earnings ? <Text tone="muted">{t('mobile.earnings.failed')}</Text> : null}

      {earnings ? (
        <>
          <HeroCard style={styles.hero}>
            <Text variant="caption" weight="bold" style={styles.eyebrow}>
              {t('mobile.earnings.thisMonth').toUpperCase()}
            </Text>
            <Text variant="headline" style={styles.light}>
              {money(earnings.net_this_month, i18n.language)}
            </Text>
            <Text style={styles.muted}>
              {ready
                ? t('mobile.earnings.nextPayout', { day: nextDay, amount: money(earnings.pending, i18n.language) })
                : t('mobile.earnings.notSetUp', { amount: money(earnings.pending, i18n.language) })}
            </Text>
          </HeroCard>

          {groups.length === 0 ? (
            <EmptyState icon={<Icon icon={Wallet} color="action" size={28} />} title={t('mobile.earnings.emptyTitle')} description={t('mobile.earnings.empty')} />
          ) : (
            <Card>
              {groups.map((group) => (
                <Pressable
                  key={group.key}
                  accessibilityRole="button"
                  accessibilityLabel={`${t('mobile.earnings.group', { product: group.product, count: group.count })}, ${money(group.amount, i18n.language)}`}
                  onPress={() => setOpen(group)}
                  style={({ pressed }) => [styles.row, pressed && styles.pressed]}
                >
                  <Text style={styles.flex}>{t('mobile.earnings.group', { product: group.product, count: group.count })}</Text>
                  <Text weight="bold">{money(group.amount, i18n.language)}</Text>
                </Pressable>
              ))}
            </Card>
          )}

          <Text variant="caption" tone="muted">
            {t('mobile.earnings.stripeNote', { percent: earnings.fee_percent })}
          </Text>
        </>
      ) : null}

      <Sheet open={open !== null} onClose={() => setOpen(null)} title={open?.product ?? ''}>
        {open?.lines.map((line) => (
          <View key={line.id} style={styles.row}>
            <View style={styles.flex}>
              <Text>{t(`mobile.earnings.roles.${line.role}`)}</Text>
              {line.date ? (
                <Text variant="caption" tone="muted">
                  {new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${line.date}T00:00:00Z`))} · {t(`mobile.earnings.status.${line.status}`)}
                </Text>
              ) : null}
            </View>
            <Text weight="bold">{money(line.amount, i18n.language)}</Text>
          </View>
        ))}
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  hero: { gap: theme.space.xxs },
  eyebrow: { color: theme.colors.heroMuted, letterSpacing: 1 },
  light: { color: theme.colors.heroText },
  muted: { color: theme.colors.heroMuted },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md, minHeight: theme.touch },
  pressed: { opacity: 0.7 },
  flex: { flex: 1 },
}));
