import { router, useLocalSearchParams } from 'expo-router';
import { Check, Globe } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { deviceInfo } from '@/lib/session';
import { updateDraft } from '@/lib/onboarding';
import { Button, Callout, Card, Eyebrow, Icon, Screen, Text } from '@/ui';

/**
 * State not live yet (mobile journey screen 3): what waitlist members get
 * and how many are waiting. Joining is once per phone; the super admin sees
 * the count on States.
 */
export default function WaitlistScreen() {
  const { t } = useTranslation();
  const params = useLocalSearchParams<{ state: string; name: string; waiting?: string }>();
  const [waiting, setWaiting] = useState(Number(params.waiting ?? 0));
  const [joined, setJoined] = useState(false);
  const [failed, setFailed] = useState(false);
  const [busy, setBusy] = useState(false);
  const state = params.name;

  const join = async () => {
    setBusy(true);
    setFailed(false);
    const { id } = await deviceInfo();
    const { data, response } = await api().POST('/states/{state}/waitlist', { params: { path: { state: params.state } }, body: { device_id: id } });
    setBusy(false);

    if (response.ok && data) {
      setWaiting(data.waiting);
      setJoined(true);
    } else {
      setFailed(true);
    }
  };

  const texas = () => {
    updateDraft({ state: 'TX' });
    router.replace('/state');
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {joined ? null : <Button label={t('mobile.onboarding.waitlist.join', { state })} loading={busy} fullWidth onPress={() => void join()} />}
          <Button label={t('mobile.onboarding.waitlist.texasInstead')} variant="secondary" fullWidth onPress={texas} />
        </View>
      }
    >
      <View style={styles.icon}>
        <Icon icon={Globe} color="action" size={28} />
      </View>
      <Text variant="title" heading style={styles.center}>
        {t('mobile.onboarding.waitlist.title', { state })}
      </Text>
      <Text tone="muted" style={styles.center}>
        {t('mobile.onboarding.waitlist.body', { state })}
      </Text>

      <Card>
        <Eyebrow>{t('mobile.onboarding.waitlist.youGet')}</Eyebrow>
        {(['early', 'credits', 'say'] as const).map((key) => (
          <View key={key} style={styles.benefit}>
            <Icon icon={Check} color="success" size={20} />
            <Text style={styles.flex}>{t(`mobile.onboarding.waitlist.${key}`)}</Text>
          </View>
        ))}
      </Card>

      {waiting > 0 ? (
        <Text variant="caption" tone="muted" style={styles.center}>
          {t('mobile.onboarding.waitlist.waiting', { count: waiting, state })}
        </Text>
      ) : null}
      {joined ? <Callout tone="success" live title={t('mobile.onboarding.waitlist.joined', { state })} /> : null}
      {failed ? <Callout tone="danger" live title={t('mobile.onboarding.waitlist.joinFailed')} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  footer: { gap: theme.space.sm, paddingHorizontal: theme.m.gutter, paddingBottom: theme.space.xl },
  icon: {
    alignSelf: 'center',
    width: theme.m.avatarLarge,
    height: theme.m.avatarLarge,
    borderRadius: theme.m.radius.hero,
    backgroundColor: theme.colors.actionTint,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: theme.space.xl,
  },
  center: { textAlign: 'center' },
  benefit: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
}));
