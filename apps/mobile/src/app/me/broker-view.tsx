import { router, useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ProfileView } from '@/features/oversight/ProfileView';
import { loadBrokerView, type OversightProfile } from '@/lib/brokerage';
import { Callout, Screen, Text, TitleBar } from '@/ui';

/** What my broker sees (A16.1): the agent's own summary, exactly as their broker sees it. */
export default function BrokerViewScreen() {
  const { t } = useTranslation();
  const [profile, setProfile] = useState<OversightProfile | null | undefined>(undefined);

  useFocusEffect(
    useCallback(() => {
      void loadBrokerView().then(setProfile);
    }, []),
  );

  return (
    <Screen>
      <TitleBar title={t('brokerage.oversight.mine.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.oversight.mine.purpose')}</Text>
      {profile === null ? <Callout tone="info" title={t('brokerage.oversight.mine.none')} /> : null}
      {profile ? <ProfileView profile={profile} /> : null}
    </Screen>
  );
}
