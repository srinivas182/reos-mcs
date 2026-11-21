import { router, useLocalSearchParams } from 'expo-router';
import { Check } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { connectTool, toolInitials, useConnections } from '@/lib/connections';
import { dayAndMonth } from '@/lib/time';
import { Button, Callout, Card, Chip, Eyebrow, Icon, Screen, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Reconnect (Connections and Sync journey, screen 27): why it stopped in plain
 * words, what reconnecting does, and one button that signs in to the tool
 * again in a secure browser sheet. REOS has already stopped retrying a
 * refused sign-in, so nothing happens until the person signs in.
 */
export default function ReconnectScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { connections, reload } = useConnections();
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);
  const connection = connections?.find((c) => String(c.id) === id);

  if (!connection) {
    return (
      <Screen>
        <TitleBar title={t('connections.alert.label')} onBack={() => router.back()} />
      </Screen>
    );
  }

  const tool = connection.integration_name;
  const why =
    connection.problem === 'connections.errors.refused'
      ? t('connections.reconnectScreen.why.refused', { tool })
      : connection.problem === 'connections.errors.modeChanged'
        ? t('connections.reconnectScreen.why.modeChanged', { tool })
        : t('connections.reconnectScreen.why.other', { tool });
  const since = connection.last_synced_at ?? connection.problem_since;
  const steps = [
    t('connections.reconnectScreen.next1', { tool }),
    since ? t('connections.reconnectScreen.next2', { date: dayAndMonth(since, i18n.language) }) : null,
    t('connections.reconnectScreen.next3'),
  ].filter((step): step is string => step !== null);

  const reconnect = async () => {
    setBusy(true);
    setProblem(null);
    const result = await connectTool(connection.integration);
    setBusy(false);

    if (result.outcome === 'connected') {
      await reload();
      toast({ message: t('connections.outcome.connected', { tool }) });
      router.back();
    } else if (result.outcome === 'failed') {
      setProblem(result.reason && result.reason.startsWith('connections.') ? t(result.reason as 'connections.errors.refused') : (result.reason ?? t('connections.errors.vendorUnavailable')));
    }
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          <Button label={t('connections.reconnectScreen.signIn', { tool })} loading={busy} fullWidth onPress={() => void reconnect()} />
        </View>
      }
    >
      <TitleBar title={t('connections.reconnectScreen.title', { tool })} onBack={() => router.back()} />

      {connection.status === 'reconnect_needed' ? (
        <Card>
          <View style={styles.header}>
            <View style={styles.tile}>
              <Text weight="bold" tone="onAction" heading={false}>
                {toolInitials(tool)}
              </Text>
            </View>
            <View style={styles.flex}>
              <Text weight="bold" heading={false}>
                {t('connections.reconnectScreen.expired')}
              </Text>
              <Text variant="caption" tone="muted">
                {why}
              </Text>
            </View>
            <Chip label={t('connections.reconnectScreen.chip')} tone="danger" />
          </View>
        </Card>
      ) : (
        <Callout tone="success" title={t('connections.reconnectScreen.working')} />
      )}

      <Card>
        <Eyebrow>{t('connections.reconnectScreen.nextTitle')}</Eyebrow>
        {steps.map((step) => (
          <View key={step} style={styles.step}>
            <Icon icon={Check} color="success" size={20} />
            <Text style={styles.flex}>{step}</Text>
          </View>
        ))}
      </Card>
      {connection.account ? (
        <Text variant="caption" tone="muted">
          {connection.account}
        </Text>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  header: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  tile: {
    width: theme.touch,
    height: theme.touch,
    borderRadius: theme.m.radius.button,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: theme.colors.action,
  },
  flex: { flex: 1 },
  step: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, minHeight: theme.touch - theme.space.md },
}));
