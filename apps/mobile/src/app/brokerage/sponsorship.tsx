import { router, useFocusEffect } from 'expo-router';
import { UserMinus } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadSponsorships, releaseAgent, type SponsorshipBoard } from '@/lib/brokerage';
import { Button, Callout, ChoiceRow, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Sponsorship on the phone (A16.2): joins and releases with TREC's
 * confirmation, and releasing an agent with a handover of open deals.
 */
export default function SponsorshipScreen() {
  const { t, i18n } = useTranslation();
  const [board, setBoard] = useState<SponsorshipBoard | null>(null);
  const [agent, setAgent] = useState<number | null>(null);
  const [handover, setHandover] = useState<number | null>(null);
  const [date, setDate] = useState(new Date().toISOString().slice(0, 10));
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadSponsorships().then(setBoard);
    }, []),
  );

  const chosen = board?.agents.find((a) => a.id === agent) ?? null;
  const show = (iso: string) => new Date(`${iso}T12:00:00`).toLocaleDateString(i18n.language);

  return (
    <Screen>
      <TitleBar title={t('brokerage.sponsorship.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.sponsorship.purpose')}</Text>
      {message ? <Callout tone={message.tone} title={message.text} /> : null}
      {board?.trec_number === null ? <Callout tone="warning" title={t('brokerage.sponsorship.noTrec')} /> : null}
      {board && board.changes.length === 0 ? <EmptyState icon={<Icon icon={UserMinus} color="action" size={32} />} title={t('brokerage.sponsorship.emptyTitle')} description={t('brokerage.sponsorship.empty')} /> : null}
      {board?.changes.map((c) => (
        <ListRow
          key={c.id}
          icon={UserMinus}
          tone={c.status === 'confirmed' ? 'success' : 'info'}
          title={t('brokerage.sponsorship.row', { kind: c.kind === 'join' ? t('brokerage.sponsorship.kinds.join') : t('brokerage.sponsorship.kinds.release'), agent: c.agent, date: show(c.effective_on) })}
          subtitle={[
            c.status === 'confirmed' ? t('brokerage.sponsorship.statuses.confirmed') : c.kind === 'join' || c.status === 'done' ? t('brokerage.sponsorship.statuses.done') : t('brokerage.sponsorship.statuses.recorded'),
            c.handover_to ? t('brokerage.sponsorship.handedOver', { count: c.deals, to: c.handover_to }) : null,
          ]
            .filter(Boolean)
            .join(' · ')}
        />
      ))}
      {board && board.agents.length > 0 ? (
        <>
          <SectionTitle>{t('brokerage.sponsorship.release')}</SectionTitle>
          {board.agents.map((a) => (
            <ChoiceRow key={a.id} title={a.name} detail={a.open_deals === 0 ? t('brokerage.sponsorship.noDeals') : t('brokerage.sponsorship.openDeals', { count: a.open_deals })} selected={agent === a.id} onPress={() => setAgent(a.id)} />
          ))}
          <TextField label={t('brokerage.sponsorship.date')} value={date} maxLength={10} onChangeText={setDate} />
          {chosen && chosen.open_deals > 0 ? (
            <>
              <Text weight="medium">{t('brokerage.sponsorship.handover')}</Text>
              {board.handover
                .filter((p) => p.id !== chosen.id)
                .map((p) => (
                  <ChoiceRow key={p.id} title={p.name} selected={handover === p.id} onPress={() => setHandover(p.id)} />
                ))}
            </>
          ) : null}
          <Button
            variant="secondary"
            label={t('brokerage.sponsorship.submit')}
            loading={busy}
            disabled={chosen === null}
            onPress={() => {
              if (!chosen) return;
              setBusy(true);
              setMessage(null);
              releaseAgent(chosen.id, date, chosen.open_deals > 0 ? handover : null)
                .then((next) => {
                  setBoard(next);
                  setMessage({ tone: 'success', text: t('brokerage.sponsorship.released', { agent: chosen.name, date: show(date) }) });
                })
                .catch((e: Error) => setMessage({ tone: 'danger', text: e.message }))
                .finally(() => setBusy(false));
            }}
          />
        </>
      ) : null}
    </Screen>
  );
}
