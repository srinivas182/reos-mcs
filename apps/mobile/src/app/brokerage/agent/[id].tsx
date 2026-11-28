import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { ProfileView } from '@/features/oversight/ProfileView';
import { loadOversightAgent, type OversightProfile } from '@/lib/brokerage';
import { Callout, Screen, TitleBar } from '@/ui';

/** An agent as their broker sees them, on the phone (A16.1). */
export default function OversightAgentScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [profile, setProfile] = useState<OversightProfile | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    void loadOversightAgent(Number(id)).then((p) => {
      setProfile(p);
      setMissing(p === null);
    });
  }, [id]);

  return (
    <Screen>
      <TitleBar title={profile?.agent.name ?? t('brokerage.oversight.title')} onBack={() => router.back()} />
      {missing ? <Callout tone="info" title={t('brokerage.oversight.allGood')} /> : null}
      {profile ? <ProfileView profile={profile} /> : null}
    </Screen>
  );
}
