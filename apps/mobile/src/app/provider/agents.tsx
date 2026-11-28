import { router, useFocusEffect } from 'expo-router';
import { Send, UserRound, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { askToConnect, LEVELS, loadAgentsInArea, loadSentRequests, usageShare, withdrawRequest, type AgentLevel, type AgentsInArea, type AreaAgent, type SentRequest } from '@/lib/providers';
import { Button, Callout, EmptyState, Icon, ListRow, PillGroup, ProgressBar, Screen, SectionTitle, Sheet, Text, TextField, TitleBar } from '@/ui';

const STATUSES = ['pending', 'accepted', 'declined', 'expired', 'withdrawn'] as const;

/**
 * Agents in my areas on the phone (B16.1, provider journey): REOS agents in
 * the provider's areas by level, and Ask to connect within the plan's
 * monthly limit. First names only; contacts come when both agree.
 */
export default function AgentsInAreaScreen() {
  const { t, i18n } = useTranslation();
  const [level, setLevel] = useState<AgentLevel | 'all'>('all');
  const [view, setView] = useState<AgentsInArea | null>(null);
  const [sent, setSent] = useState<SentRequest[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [asking, setAsking] = useState<AreaAgent | null>(null);
  const [note, setNote] = useState('');
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState<string | null>(null);

  const load = useCallback(() => {
    loadAgentsInArea(level === 'all' ? null : level)
      .then(setView)
      .catch((e: Error) => setError(e.message));
    void loadSentRequests().then(setSent);
  }, [level]);

  useFocusEffect(load);

  const date = (iso: string) => new Date(iso.length === 10 ? `${iso}T12:00:00` : iso).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
  const levelName = (l: AgentLevel) => t(`providers.agents.levels.${l}`);
  const status = (s: string) => {
    const found = STATUSES.find((x) => x === s);

    return found ? t(`providers.agents.statuses.${found}`) : s;
  };
  const send = () => {
    if (!asking) return;
    setBusy(true);
    setError(null);
    askToConnect(asking.id, note)
      .then(() => {
        setDone(t('providers.agents.sent', { name: asking.name }));
        setAsking(null);
        setNote('');
        load();
      })
      .catch((e: Error) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Screen>
      <TitleBar title={t('providers.agents.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.agents.purpose')}</Text>
      {done ? <Callout tone="success" title={done} /> : null}
      {error && !asking ? <Callout tone="danger" title={error} /> : null}
      {view ? (
        <>
          <Text>{t('providers.agents.usage', { used: view.usage.used, limit: view.usage.limit, date: date(view.usage.resets_on) })}</Text>
          <ProgressBar value={usageShare(view.usage)} label={t('providers.agents.usageLeft', { count: view.usage.left })} color={view.usage.left === 0 ? 'danger' : 'action'} />
          <Text tone="muted">{t('providers.agents.privacy')}</Text>
          <PillGroup label={t('providers.agents.filters.level')} value={level} options={[{ value: 'all' as const, label: t('providers.agents.filters.all') }, ...LEVELS.map((l) => ({ value: l, label: levelName(l) }))]} onChange={setLevel} />
          {view.agents.length === 0 ? <EmptyState icon={<Icon icon={Users} color="action" size={32} />} title={t('providers.agents.empty')} description={t('providers.agents.emptyHint')} /> : null}
          {view.agents.map((a) => (
            <ListRow
              key={a.id}
              icon={UserRound}
              tone={a.level === 'top' ? 'success' : 'info'}
              title={a.brokerage ? `${a.name} · ${a.brokerage}` : a.name}
              subtitle={[levelName(a.level), t('providers.agents.sides', { count: a.sides }), a.cities.join(', '), a.request && !a.can_request ? status(a.request.status) : null].filter(Boolean).join(' · ')}
              onPress={a.can_request && view.usage.left > 0 ? () => setAsking(a) : undefined}
            />
          ))}
          <SectionTitle>{t('providers.agents.sentTitle')}</SectionTitle>
          {sent.length === 0 ? <Text tone="muted">{t('providers.agents.sentEmptyHint')}</Text> : null}
          {sent.map((r) => (
            <ListRow
              key={r.id}
              icon={Send}
              title={`${r.agent} · ${status(r.status)}`}
              subtitle={r.status === 'pending' ? t('providers.agents.expires', { date: date(r.expires_at) }) : t('providers.agents.sentOn', { date: date(r.sent_at) })}
              onPress={
                r.status === 'pending'
                  ? () =>
                      void withdrawRequest(r.id)
                        .then(() => setDone(t('providers.agents.withdrawn')))
                        .then(load)
                  : undefined
              }
            />
          ))}
        </>
      ) : null}
      <Sheet open={asking !== null} onClose={() => setAsking(null)} title={t('providers.agents.dialog.title', { name: asking?.name ?? '' })}>
        <Text tone="muted">{t('providers.agents.dialog.description', { name: asking?.name ?? '' })}</Text>
        <TextField label={t('providers.agents.dialog.note')} hint={t('providers.agents.dialog.noteHint')} value={note} onChangeText={setNote} maxLength={500} multiline error={error ?? undefined} />
        <Button icon={Send} label={t('providers.agents.dialog.send')} loading={busy} onPress={send} />
      </Sheet>
    </Screen>
  );
}
