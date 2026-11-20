import { router } from 'expo-router';
import { ArrowLeft, CreditCard, Download, Trash2 } from 'lucide-react-native';
import { useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api, webBaseUrl } from '@/lib/api';
import { errorMessage, useAuth } from '@/lib/auth';
import { clearToken } from '@/lib/session';
import { Button, Callout, Icon, IconButton, ListRow, Screen, ScreenHeader, SectionTitle, Text } from '@/ui';

/**
 * Delete account (journey screen SH/delete; both app stores require it).
 * Needs a sign-in from the last 15 minutes; otherwise the person signs in
 * again first. Deleted after 30 days; signing in before then keeps it.
 */
export default function DeleteAccountScreen() {
  const { t } = useTranslation();
  const { signOut } = useAuth();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [needsSignIn, setNeedsSignIn] = useState(false);

  const remove = async () => {
    setBusy(true);
    setError(null);
    const { response, error: failure } = await api().DELETE('/me');
    setBusy(false);

    if (response.ok) {
      // The server already signed every device out; forget the token here too.
      await clearToken();
      await signOut();

      return;
    }

    setNeedsSignIn(response.status === 403);
    setError(response.status === 403 ? t('mobile.deleteAccount.signInAgain') : errorMessage(failure));
  };

  return (
    <Screen
      footer={
        <View style={styles.footer}>
          {needsSignIn ? (
            <Button label={t('mobile.signIn.welcomeBack')} fullWidth onPress={() => void signOut()} />
          ) : (
            <Button label={t('mobile.deleteAccount.delete')} variant="destructive" loading={busy} fullWidth onPress={() => void remove()} />
          )}
          <Button label={t('mobile.deleteAccount.keep')} variant="secondary" fullWidth onPress={() => router.back()} />
        </View>
      }
    >
      <IconButton icon={ArrowLeft} label={t('common.back')} onPress={() => router.back()} />
      <ScreenHeader title={t('mobile.deleteAccount.title')} />
      <View style={styles.tile}>
        <Icon icon={Trash2} color="danger" size={28} />
      </View>
      <Text>{t('mobile.deleteAccount.intro')}</Text>
      {error ? <Callout tone={needsSignIn ? 'warning' : 'danger'} title={error} live /> : null}
      <SectionTitle>{t('mobile.deleteAccount.beforeYouGo')}</SectionTitle>
      <View style={rowStyles.list}>
        <ListRow icon={Download} title={t('mobile.deleteAccount.download')} onPress={() => void Linking.openURL(`${webBaseUrl()}/settings/profile`)} />
        <ListRow icon={CreditCard} title={t('mobile.deleteAccount.membership')} onPress={() => void Linking.openURL(webBaseUrl())} />
      </View>
      <Text variant="caption" tone="muted">
        {t('mobile.deleteAccount.kept')}
      </Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  tile: { width: 56, height: 56, borderRadius: theme.m.radius.tile, backgroundColor: theme.colors.dangerSurface, alignItems: 'center', justifyContent: 'center' },
  footer: { gap: theme.space.md },
}));

const rowStyles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
}));
