import { router, useFocusEffect } from 'expo-router';
import { Swords } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Share } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { ChallengeCard } from '@/features/practice/ChallengeCard';
import { type ChallengeBoard, createChallenge, loadChallenges } from '@/lib/practice';
import { Button, Callout, EmptyState, Icon, Screen, Text, TitleBar } from '@/ui';

/**
 * Challenges (candidate screen 52, A9.3b): Challenge a friend shares a link
 * to the same 10 questions; every challenge sent or accepted shows its
 * board, first names only. Hints cost points here only.
 */
export default function ChallengesScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const [challenges, setChallenges] = useState<ChallengeBoard[] | null>(null);
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      void loadChallenges()
        .catch(() => [])
        .then((found) => active && setChallenges(found));

      return () => {
        active = false;
      };
    }, []),
  );

  const create = async () => {
    setBusy(true);
    const made = await createChallenge().catch(() => null);
    setBusy(false);
    setFailed(made === null);
    if (!made) return;
    setChallenges((all) => [made, ...(all ?? [])]);
    await Share.share({ message: t('practice.challenges.shareMessage', { link: made.link }) }).catch(() => undefined);
    if (made.attempt_id !== null) router.push({ pathname: '/practice/[id]', params: { id: String(made.attempt_id) } });
  };

  return (
    <Screen>
      <TitleBar title={t('practice.challenges.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.challenges.description')}</Text>
      <Button icon={Swords} label={t('practice.challenges.create')} loading={busy} onPress={() => void create()} fullWidth />
      {failed ? <Callout tone="danger" title={t('common.tryAgain')} live /> : null}
      {challenges === null ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : challenges.length === 0 ? (
        <EmptyState icon={<Icon icon={Swords} color="action" size={32} />} title={t('practice.challenges.emptyTitle')} description={t('practice.challenges.empty')} />
      ) : (
        challenges.map((c) => <ChallengeCard key={c.uuid} challenge={c} />)
      )}
    </Screen>
  );
}
