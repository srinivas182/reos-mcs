import { router, useFocusEffect } from 'expo-router';
import { Check, Handshake, ShieldOff, X } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { acceptRequest, declineRequest, loadNetwork, savePreferences, type AgentNetwork, type PendingRequest } from '@/lib/providers';
import { Button, Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Sheet, SwitchRow, Text, TitleBar } from '@/ui';

/**
 * Provider requests on the phone (B16.2, agent journey): waiting requests
 * to accept or decline, connections, and which providers may ask.
 */
export default function ProviderRequestsScreen() {
  const { t, i18n } = useTranslation();
  const [network, setNetwork] = useState<AgentNetwork | null>(null);
  const [open, setOpen] = useState<PendingRequest | null>(null);
  const [message, setMessage] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  const load = useCallback(() => {
    void loadNetwork().then(setNetwork);
  }, []);

  useFocusEffect(load);

  const date = (iso: string) => new Date(iso).toLocaleDateString(i18n.language, { dateStyle: 'medium' });
  const run = (work: () => Promise<string>) => {
    work()
      .then((text) => {
        setMessage({ tone: 'success', text });
        setOpen(null);
        load();
      })
      .catch((e: Error) => setMessage({ tone: 'danger', text: e.message }));
  };
  const setPrefs = (all: boolean, off: string[]) => {
    if (!network) return;
    void savePreferences(all, off).then((preferences) => setNetwork({ ...network, preferences }));
  };
  const off = network ? network.preferences.categories.filter((c) => !c.on).map((c) => c.key) : [];

  return (
    <Screen>
      <TitleBar title={t('providers.network.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.network.purpose')}</Text>
      {message ? <Callout tone={message.tone} title={message.text} /> : null}
      {network ? (
        <>
          <SectionTitle>{t('providers.network.pending')}</SectionTitle>
          {network.pending.length === 0 ? <Text tone="muted">{t('providers.network.pendingEmptyHint')}</Text> : null}
          {network.pending.map((r) => (
            <ListRow
              key={r.id}
              icon={Handshake}
              tone="info"
              title={r.provider.category ? `${r.provider.name} · ${r.provider.category}` : r.provider.name}
              subtitle={t('providers.agents.expires', { date: date(r.expires_at) })}
              onPress={() => setOpen(r)}
            />
          ))}
          <SectionTitle>{t('providers.network.connections')}</SectionTitle>
          {network.connections.length === 0 ? <EmptyState icon={<Icon icon={Handshake} color="action" size={32} />} title={t('providers.network.connectionsEmpty')} description={t('providers.network.connectionsEmptyHint')} /> : null}
          {network.connections.map((c) => (
            <ListRow
              key={c.id}
              icon={Handshake}
              tone={c.contacts ? 'success' : 'info'}
              title={c.provider.name}
              subtitle={c.blocked ? t('providers.network.blocked') : c.contacts ? t('providers.network.contacts') : t('providers.network.notShared')}
              onPress={() => router.push({ pathname: '/provider-connection/[id]', params: { id: c.id } })}
            />
          ))}
          <SectionTitle>{t('providers.network.preferences.title')}</SectionTitle>
          <SwitchRow label={t('providers.network.preferences.all')} value={network.preferences.all} onValueChange={(all) => setPrefs(all, off)} />
          {network.preferences.all ? network.preferences.categories.map((c) => <SwitchRow key={c.key} label={c.name} value={c.on} onValueChange={(on) => setPrefs(true, on ? off.filter((k) => k !== c.key) : [...off, c.key])} />) : null}
        </>
      ) : null}
      <Sheet open={open !== null} onClose={() => setOpen(null)} title={open?.provider.name ?? ''}>
        {open?.message ? <Text>{open.message}</Text> : null}
        <Button icon={Check} label={t('providers.network.accept')} onPress={() => open && run(async () => (await acceptRequest(open.id), t('providers.network.accepted', { name: open.provider.name })))} />
        <Button icon={X} variant="secondary" label={t('providers.network.decline')} onPress={() => open && run(async () => (await declineRequest(open.id, false), t('providers.network.declined', { name: open.provider.name })))} />
        <Button icon={ShieldOff} variant="secondary" label={t('providers.network.declineBlock')} onPress={() => open && run(async () => (await declineRequest(open.id, true), t('providers.network.declined', { name: open.provider.name })))} />
      </Sheet>
    </Screen>
  );
}
