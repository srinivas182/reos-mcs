import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ArrowRight, Check, Lock } from 'lucide-react-native';
import { useState } from 'react';
import { Platform, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { emailMeTheLink, redeemCode, type RedeemedCode, useMembership } from '@/lib/membership';
import { Button, Card, Chip, Icon, Screen, Sheet, Text, TextField, TitleBar } from '@/ui';

/**
 * Membership (candidate app journey screen 6, B5.1b): shown when free
 * limits are used. No price in the app. iPhone: "Email me the link" (or
 * Continue on reos.com when REOS switches it on); Android shows the same
 * words without a link. A school, brokerage or association's code unlocks a
 * sponsored seat at once. A purchase on the web unlocks this screen within
 * seconds of coming back to the app.
 */
export default function MembershipScreen() {
  const { t } = useTranslation();
  const { user } = useAuth();
  const { membership, reload } = useMembership();
  const [sent, setSent] = useState(false);
  const [entering, setEntering] = useState(false);
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [redeemed, setRedeemed] = useState<RedeemedCode | null>(null);
  const ios = Platform.OS === 'ios';

  const email = async () => {
    setBusy(true);
    setSent(await emailMeTheLink().catch(() => false));
    setBusy(false);
  };

  const applyCode = async () => {
    setBusy(true);
    setError(null);
    const result = await redeemCode(code.trim()).catch(() => ({ error: t('common.tryAgain') }));
    setBusy(false);

    if ('error' in result) {
      setError(result.error);

      return;
    }

    setEntering(false);
    setRedeemed(result);
    await reload();
  };

  if (redeemed || membership?.active) {
    return (
      <Screen>
        <TitleBar title={t('mobile.membership.unlockedTitle')} onBack={() => router.back()} />
        <Card>
          <Icon icon={Check} color="success" size={32} />
          <Text variant="title" heading>
            {t('mobile.membership.unlockedTitle')}
          </Text>
          {redeemed ? <Text>{t('mobile.membership.unlocked', { product: redeemed.product, sponsor: redeemed.sponsor })}</Text> : null}
        </Card>
        <Button label={t('mobile.membership.done')} onPress={() => router.replace('/learn')} />
      </Screen>
    );
  }

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {ios && membership?.ios_buy_link === 'link' && membership.buy_url ? (
            <Button label={t('mobile.membership.continue')} trailingIcon={ArrowRight} fullWidth onPress={() => void WebBrowser.openBrowserAsync(membership.buy_url ?? '')} />
          ) : ios ? (
            <Button label={t('mobile.membership.emailMe')} fullWidth loading={busy && !entering} disabled={sent || !user?.email} onPress={() => void email()} />
          ) : null}
          <Button label={t('mobile.membership.enterCode')} variant="secondary" fullWidth onPress={() => setEntering(true)} />
          <Text variant="caption" tone="muted" style={styles.center}>
            {ios ? t('mobile.membership.iosNote') : t('mobile.membership.android')}
          </Text>
        </View>
      }
    >
      <View style={styles.top}>
        <View style={styles.lock}>
          <Icon icon={Lock} color="action" />
        </View>
        <Chip label={t('mobile.membership.freePlan')} tone="neutral" />
      </View>
      <Text variant="title" heading>
        {t('mobile.membership.title')}
      </Text>
      <Text tone="muted">{t('mobile.membership.body')}</Text>

      {(membership?.includes.length ?? 0) > 0 ? (
        <Card>
          <Text weight="semibold">{t('mobile.membership.includes')}</Text>
          {membership?.includes.map((item) => (
            <View key={item} style={styles.row}>
              <Icon icon={Check} color="success" size={20} />
              <Text style={styles.flex}>{item}</Text>
            </View>
          ))}
        </Card>
      ) : null}

      <Card onPress={() => setEntering(true)} accessibilityLabel={`${t('mobile.membership.sponsorTitle')} ${t('mobile.membership.sponsorBody')}`} style={styles.sponsor}>
        <Text weight="semibold" tone="action">
          {t('mobile.membership.sponsorTitle')}
        </Text>
        <Text tone="action">{t('mobile.membership.sponsorBody')}</Text>
      </Card>

      {sent && user?.email ? <Text accessibilityLiveRegion="polite">{t('mobile.membership.sent', { email: user.email })}</Text> : null}
      {ios && !user?.email ? <Text tone="muted">{t('mobile.membership.noEmail')}</Text> : null}

      <Sheet open={entering} onClose={() => setEntering(false)} title={t('mobile.membership.codeTitle')}>
        <TextField label={t('billing.codes.label')} value={code} onChangeText={setCode} autoCapitalize="characters" autoCorrect={false} error={error ?? undefined} />
        <Button label={t('mobile.membership.useCode')} loading={busy} disabled={code.trim().length < 4} onPress={() => void applyCode()} />
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  top: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  lock: { width: 48, height: 48, borderRadius: theme.radius.pill, alignItems: 'center', justifyContent: 'center', backgroundColor: theme.colors.actionTint },
  row: { flexDirection: 'row', gap: theme.space.xs, alignItems: 'flex-start' },
  flex: { flex: 1 },
  sponsor: { backgroundColor: theme.colors.actionTint },
  footer: { gap: theme.space.sm },
  center: { textAlign: 'center' },
}));
