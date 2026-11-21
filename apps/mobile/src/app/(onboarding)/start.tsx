import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { BookOpen, Building2, GraduationCap, ShieldCheck, Tag, Wrench } from 'lucide-react-native';
import { useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api, webBaseUrl } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { updateDraft, type OnboardingPath } from '@/lib/onboarding';
import { BrandMark, Button, PathCard, Screen, Sheet, Text } from '@/ui';

const paths = [
  { path: 'become_agent', icon: BookOpen },
  { path: 'licensed_agent', icon: ShieldCheck },
  { path: 'broker', icon: Building2 },
  { path: 'instructor', icon: GraduationCap },
  { path: 'provider', icon: Wrench },
] as const satisfies readonly { path: OnboardingPath; icon: unknown }[];

/**
 * Choose your path (mobile journey screen 1): one question, large cards,
 * shown on first launch without an invite. Brokers, teams and associations
 * finish set-up on reos.com. The choice is saved to the account at sign-up,
 * so this shows only once.
 */
export default function StartScreen() {
  const { t } = useTranslation();
  const { status } = useAuth();
  const [brokerSheet, setBrokerSheet] = useState(false);

  const choose = async (path: OnboardingPath) => {
    if (path === 'broker') {
      setBrokerSheet(true);

      return;
    }

    updateDraft({ path });

    // Already signed in (an account without a path yet): save it now.
    if (status === 'signedIn') {
      await api().PUT('/me/onboarding', { body: { path } });
    }

    if (path === 'become_agent' || path === 'licensed_agent') {
      router.push('/state');
    } else {
      router.push(status === 'signedIn' ? '/' : '/sign-in');
    }
  };

  return (
    <Screen dark>
      <BrandMark />
      <View style={styles.titles}>
        <Text variant="headline" heading style={styles.light}>
          {t('onboarding.path.title')}
        </Text>
        <Text style={styles.muted}>{t('onboarding.path.subtitle')}</Text>
      </View>

      <View style={styles.cards}>
        {paths.map(({ path, icon }) => (
          <PathCard
            key={path}
            icon={icon}
            primary={path === 'become_agent'}
            title={t(`onboarding.path.${path}.title`)}
            subtitle={t(`onboarding.path.${path}.subtitle`)}
            onPress={() => void choose(path)}
          />
        ))}
      </View>

      <View style={styles.bottom}>
        <Button label={t('onboarding.path.code')} icon={Tag} variant="heroOutline" fullWidth onPress={() => router.push('/enter-code')} />
        {status === 'signedIn' ? null : (
          <Pressable accessibilityRole="button" onPress={() => router.push('/sign-in')} style={styles.link}>
            <Text weight="semibold" style={styles.linkText}>
              {t('onboarding.path.signIn')}
            </Text>
          </Pressable>
        )}
      </View>

      <Sheet open={brokerSheet} onClose={() => setBrokerSheet(false)} title={t('onboarding.path.brokerTitle')}>
        <Text>{t('onboarding.path.brokerBody')}</Text>
        <Button label={t('onboarding.path.openWebsite')} fullWidth onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/register?path=broker`)} />
        {status === 'signedIn' ? null : (
          <Button
            label={t('onboarding.path.signIn')}
            variant="secondary"
            fullWidth
            onPress={() => {
              setBrokerSheet(false);
              updateDraft({ path: 'broker' });
              router.push('/sign-in');
            }}
          />
        )}
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  titles: { gap: theme.space.sm, marginTop: theme.space.xl },
  light: { color: theme.colors.heroText },
  muted: { color: theme.colors.heroMuted },
  cards: { gap: theme.space.md, marginTop: theme.space.md },
  bottom: { gap: theme.space.sm, marginTop: theme.space.xl },
  link: { alignSelf: 'center', minHeight: theme.touch, justifyContent: 'center', paddingHorizontal: theme.space.lg },
  linkText: { color: theme.colors.heroAccent },
}));
