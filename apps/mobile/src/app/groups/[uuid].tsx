import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { GroupCard } from '@/features/practice/GroupCard';
import { joinGroup, loadGroup, type StudyGroup } from '@/lib/practice';
import { Button, Callout, Screen, Text, TitleBar } from '@/ui';

/** A study group's invite link (screen 52, A9.3c): its name and size, and Join (study groups on in Profile). */
export default function GroupInviteScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [group, setGroup] = useState<StudyGroup | null | undefined>(undefined);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let active = true;
    void loadGroup(String(uuid))
      .catch(() => null)
      .then((found) => active && setGroup(found));

    return () => {
      active = false;
    };
  }, [uuid]);

  const join = async () => {
    const { group: joined, error: why } = await joinGroup(String(uuid)).catch(() => ({ group: null, error: null }));
    setError(joined ? null : (why ?? t('common.tryAgain')));
    if (joined) router.replace('/practice/groups');
  };

  return (
    <Screen>
      <TitleBar title={t('practice.groups.title')} onBack={() => router.back()} />
      {group === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : group === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} />
      ) : (
        <>
          {!group.mine ? <Text tone="muted">{t('practice.groups.joinBody', { name: group.name, count: group.members })}</Text> : null}
          <GroupCard group={group} onJoin={() => void join()} />
        </>
      )}
      {error ? (
        <Callout tone="warning" title={error} action={<Button variant="secondary" label={t('practice.groups.turnOn')} onPress={() => router.push('/practice/groups')} />} live />
      ) : null}
    </Screen>
  );
}
