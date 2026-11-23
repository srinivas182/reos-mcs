import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { ChallengeCard } from '@/features/practice/ChallengeCard';
import { acceptChallenge, type ChallengeBoard, loadChallenge } from '@/lib/practice';
import { Callout, Screen, Text, TitleBar } from '@/ui';

/**
 * A challenge opened from a notification or its link (screen 52, A9.3b):
 * who sent it (first name), Accept for the same 10 questions, then the board.
 */
export default function ChallengeScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [challenge, setChallenge] = useState<ChallengeBoard | null | undefined>(undefined);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let active = true;
    void loadChallenge(String(uuid))
      .catch(() => null)
      .then((found) => active && setChallenge(found));

    return () => {
      active = false;
    };
  }, [uuid]);

  const accept = async () => {
    const taken = await acceptChallenge(String(uuid)).catch(() => null);
    setFailed(taken === null);
    if (taken?.attempt_id) router.replace({ pathname: '/practice/[id]', params: { id: String(taken.attempt_id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.challenges.title')} onBack={() => router.back()} />
      {challenge === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : challenge === null ? (
        <Callout tone="warning" title={t('practice.challenges.ended')} />
      ) : (
        <>
          {!challenge.mine && challenge.attempt_id === null ? <Text tone="muted">{t('practice.challenges.acceptBody', { name: challenge.from, count: challenge.questions })}</Text> : null}
          <ChallengeCard challenge={challenge} onAccept={() => void accept()} />
        </>
      )}
      {failed ? <Callout tone="danger" title={t('common.tryAgain')} live /> : null}
    </Screen>
  );
}
