import { router, useFocusEffect } from 'expo-router';
import { Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { ActivityIndicator } from 'react-native';
import { useTranslation } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { GroupCard } from '@/features/practice/GroupCard';
import { challengeGroup, createGroup, leaveGroup, loadGroups, setGroupsOptIn, type StudyGroups } from '@/lib/practice';
import { Button, Callout, EmptyState, Icon, MenuGroup, Screen, SwitchRow, Text, TextField, TitleBar } from '@/ui';

/**
 * Study groups (candidate screen 52, A9.3c; from Me and Practice): the
 * opt-in switch, Start a group, and each group with this week's
 * leaderboard (first names only). Turning it off leaves every group.
 */
export default function GroupsScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const [state, setState] = useState<StudyGroups | null | undefined>(undefined);
  const [name, setName] = useState('');
  const [failed, setFailed] = useState(false);

  const reload = useCallback(() => {
    let active = true;
    void loadGroups()
      .catch(() => null)
      .then((found) => active && setState(found));

    return () => {
      active = false;
    };
  }, []);
  useFocusEffect(reload);

  const toggle = async (on: boolean) => {
    const next = await setGroupsOptIn(on).catch(() => null);
    setFailed(next === null);
    if (next) setState(next);
  };
  const create = async () => {
    const made = await createGroup(name.trim()).catch(() => null);
    setFailed(made === null);
    if (made) {
      setName('');
      setState((s) => (s ? { ...s, groups: [...s.groups, made] } : s));
    }
  };
  const challenge = async (uuid: string) => {
    const made = await challengeGroup(uuid).catch(() => null);
    setFailed(made === null);
    if (made?.attempt_id) router.push({ pathname: '/practice/[id]', params: { id: String(made.attempt_id) } });
  };
  const leave = async (uuid: string) => {
    await leaveGroup(uuid).catch(() => undefined);
    setState((s) => (s ? { ...s, groups: s.groups.filter((g) => g.uuid !== uuid) } : s));
  };

  return (
    <Screen>
      <TitleBar title={t('practice.groups.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.groups.description')}</Text>
      {state === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : state === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          <MenuGroup>
            <SwitchRow label={t('practice.groups.optInLabel')} description={t('practice.groups.optInBody')} value={state.opt_in} onValueChange={(on) => void toggle(on)} />
          </MenuGroup>
          {state.opt_in ? (
            <>
              <TextField label={t('practice.groups.name')} value={name} onChangeText={setName} maxLength={80} />
              <Button label={t('practice.groups.create')} disabled={name.trim().length < 2} onPress={() => void create()} fullWidth />
              {state.groups.length === 0 ? (
                <EmptyState icon={<Icon icon={Users} color="action" size={32} />} title={t('practice.groups.emptyTitle')} description={t('practice.groups.empty')} />
              ) : (
                state.groups.map((g) => <GroupCard key={g.uuid} group={g} onChallenge={() => void challenge(g.uuid)} onLeave={() => void leave(g.uuid)} />)
              )}
            </>
          ) : (
            <Text tone="muted">{t('practice.groups.off')}</Text>
          )}
        </>
      )}
      {failed ? <Callout tone="danger" title={t('common.tryAgain')} live /> : null}
    </Screen>
  );
}
