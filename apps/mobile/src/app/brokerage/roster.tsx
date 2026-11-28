import { router, useFocusEffect } from 'expo-router';
import { Send, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { inviteAll, loadRoster, ready, type Roster } from '@/lib/brokerage';
import { Button, Callout, EmptyState, Icon, ListRow, Screen, Text, TitleBar } from '@/ui';

/**
 * Bring in your agents on the phone (A15.1b, broker journey screen 3):
 * TREC's roster for the brokerage and Invite all. Emails and phones for
 * agents not on REOS are added on the web.
 */
export default function RosterScreen() {
  const { t } = useTranslation();
  const [roster, setRoster] = useState<Roster | null>(null);
  const [note, setNote] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadRoster().then(setRoster);
    }, []),
  );

  const count = roster === null ? 0 : ready(roster);

  return (
    <Screen>
      <TitleBar title={t('brokerage.roster.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.roster.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      {note ? <Callout tone="success" title={note} /> : null}
      {roster ? (
        <>
          <Text>{t('brokerage.roster.counts', { sponsored: roster.counts.sponsored, member: roster.counts.member, invited: roster.counts.invited, ready: count, needs: roster.counts.needs_contact })}</Text>
          <Button
            icon={Send}
            label={count === 0 ? t('brokerage.roster.inviteAll') : t('brokerage.roster.inviteAllCount', { count })}
            loading={busy}
            disabled={count === 0}
            onPress={() => {
              setBusy(true);
              setError(null);
              inviteAll()
                .then((r) => {
                  setRoster(r.roster);
                  setNote([t('brokerage.roster.sent', { count: r.sent }), r.needs_contact > 0 ? t('brokerage.roster.stillNeeds', { count: r.needs_contact }) : null].filter(Boolean).join(' '));
                })
                .catch((e: Error) => setError(e.message))
                .finally(() => setBusy(false));
            }}
          />
          {roster.counts.needs_contact > 0 ? <Text tone="muted">{t('brokerage.roster.addHint')}</Text> : null}
          {roster.agents.length === 0 ? <EmptyState icon={<Icon icon={UserRound} color="action" size={32} />} title={t('brokerage.roster.none')} description={t('brokerage.roster.noneHint')} /> : null}
          {roster.agents.map((a) => (
            <ListRow
              key={a.licence}
              icon={UserRound}
              tone={a.state === 'member' ? 'success' : a.state === 'needs_contact' ? 'warning' : 'info'}
              title={a.name}
              subtitle={`${t('brokerage.roster.licence', { number: a.licence })} · ${t(`brokerage.roster.states.${a.state}`)}`}
            />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
