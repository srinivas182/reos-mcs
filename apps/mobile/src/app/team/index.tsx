import { router, useFocusEffect } from 'expo-router';
import { Inbox } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadRouting, setInRotation, type RoutingBoard } from '@/lib/team';
import { EmptyState, Icon, ListRow, Screen, SectionTitle, SwitchRow, Text, TitleBar } from '@/ui';

const SOURCES = ['website', 'zillow', 'realtor_com', 'open_house', 'referral', 'other'] as const;

/**
 * Lead routing on the phone (A17.3, team leader screen 29): who's in the
 * rotation, and recent leads with who got them. Rules are edited on the web.
 */
export default function TeamRoutingScreen() {
  const { t } = useTranslation();
  const [board, setBoard] = useState<RoutingBoard | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadRouting().then(setBoard);
    }, []),
  );

  const source = (s: string) => {
    const found = SOURCES.find((x) => x === s);

    return found ? t(`teams.routing.sourceNames.${found}`) : s;
  };
  const status = (l: RoutingBoard['leads'][number]) =>
    l.status === 'claimed'
      ? t('teams.routing.statuses.claimed', { agent: l.agent ?? '' })
      : l.status === 'assigned'
        ? t('teams.routing.statuses.assigned', { agent: l.agent ?? '' })
        : l.status === 'leader'
          ? t('teams.routing.statuses.leader')
          : t('teams.routing.statuses.offered');

  return (
    <Screen>
      <TitleBar title={t('teams.routing.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('teams.routing.purpose')}</Text>
      {board ? (
        <>
          <Text>{`${t(`teams.routing.methods.${board.rules.method}`)} · ${t('teams.routing.claimMinutes')}: ${board.rules.claim_minutes}`}</Text>
          <SectionTitle>{t('teams.routing.rotation')}</SectionTitle>
          {board.members.map((m) => (
            <SwitchRow
              key={m.id}
              label={m.name}
              description={m.languages.join(', ').toUpperCase()}
              value={m.active}
              onValueChange={(on) =>
                void setInRotation(
                  m.id,
                  on,
                  m.languages.filter((l): l is 'en' | 'es' => l === 'en' || l === 'es'),
                ).then(setBoard)
              }
            />
          ))}
          <SectionTitle>{t('teams.routing.leads')}</SectionTitle>
          {board.leads.length === 0 ? <EmptyState icon={<Icon icon={Inbox} color="action" size={32} />} title={t('teams.routing.leadsEmpty')} description={t('teams.routing.leadsEmptyHint')} /> : null}
          {board.leads.map((l) => (
            <ListRow key={l.id} icon={Inbox} title={`${l.name} · ${source(l.source)}`} subtitle={status(l)} />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
