import { router, useFocusEffect } from 'expo-router';
import { UsersRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadCommunities, type CommunityCard } from '@/lib/communities';
import { EmptyState, Icon, ListRow, Screen, Text, TitleBar } from '@/ui';

/** Communities I've joined (B17.2), each led by a provider in the network. */
export default function CommunitiesScreen() {
  const { t, i18n } = useTranslation();
  const [list, setList] = useState<CommunityCard[] | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadCommunities().then(setList);
    }, []),
  );

  const when = (iso: string) => new Date(iso).toLocaleString(i18n.language, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });

  return (
    <Screen>
      <TitleBar title={t('communities.mineTitle')} onBack={() => router.back()} />
      <Text tone="muted">{t('communities.minePurpose')}</Text>
      {list && list.length === 0 ? <EmptyState icon={<Icon icon={UsersRound} color="action" size={32} />} title={t('communities.mineEmpty')} description={t('communities.mineEmptyHint')} /> : null}
      {list?.map((c) => (
        <ListRow
          key={c.id}
          icon={UsersRound}
          tone="info"
          title={c.name}
          subtitle={[t('communities.ledBy', { name: c.owner }), c.next ? t('communities.nextSession', { title: c.next.title, date: when(c.next.starts_at) }) : t('communities.members', { count: c.members })].join(' · ')}
          onPress={() => router.push({ pathname: '/communities/[uuid]', params: { uuid: c.id } })}
        />
      ))}
    </Screen>
  );
}
