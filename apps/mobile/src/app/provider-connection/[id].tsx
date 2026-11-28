import { router, useLocalSearchParams } from 'expo-router';
import { Mail, MessageSquare, Phone, ShieldOff, Share2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { blockConnection, loadConnection, sharingState, shareContacts, type ProviderConnection } from '@/lib/providers';
import { Button, Callout, ListRow, Screen, Text, TitleBar } from '@/ui';

/**
 * A provider connection on the phone (B16.2), for either side: Share my
 * phone and email (shown only when both have shared), Message and Block.
 */
export default function ConnectionScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [c, setC] = useState<ProviderConnection | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void loadConnection(id).then(setC);
  }, [id]);

  if (!c) {
    return (
      <Screen>
        <TitleBar title={t('providers.network.connections')} onBack={() => router.back()} />
      </Screen>
    );
  }

  const title = c.side === 'agent' ? c.provider.name : c.with;
  const state = sharingState(c);
  const toggle = () => {
    setBusy(true);
    shareContacts(c.id, !c.i_shared)
      .then(setC)
      .catch((e: Error) => setError(e.message))
      .finally(() => setBusy(false));
  };

  return (
    <Screen>
      <TitleBar title={title} onBack={() => router.back()} />
      {c.provider.category && c.side === 'agent' ? <Text tone="muted">{c.provider.category}</Text> : null}
      {error ? <Callout tone="danger" title={error} /> : null}
      {c.blocked ? (
        <Callout tone="info" title={t('providers.network.blocked')} />
      ) : (
        <>
          {state === 'both' && c.contacts ? (
            <>
              {c.contacts.email ? <ListRow icon={Mail} tone="success" title={t('providers.network.email', { email: c.contacts.email })} /> : null}
              {c.contacts.phone ? <ListRow icon={Phone} tone="success" title={t('providers.network.phone', { phone: c.contacts.phone })} /> : null}
            </>
          ) : (
            <Text tone="muted">{state === 'waiting_them' ? t('providers.network.waitingThem', { name: c.with }) : state === 'waiting_me' ? t('providers.network.waitingMe', { name: c.with }) : t('providers.network.notShared')}</Text>
          )}
          <Button icon={Share2} variant={c.i_shared ? 'secondary' : 'primary'} label={c.i_shared ? t('providers.network.unshare') : t('providers.network.share')} loading={busy} onPress={toggle} />
          {c.conversation ? <Button icon={MessageSquare} variant="secondary" label={t('providers.network.messageFor', { name: c.with })} onPress={() => router.push({ pathname: '/messages/[id]', params: { id: c.conversation ?? '' } })} /> : null}
          <Button
            icon={ShieldOff}
            variant="secondary"
            label={t('providers.network.blockFor', { name: c.with })}
            onPress={() =>
              void blockConnection(c.id)
                .then(() => setC({ ...c, blocked: true, contacts: null, conversation: null }))
                .catch((e: Error) => setError(e.message))
            }
          />
          <Text tone="muted">{t('providers.network.report')}</Text>
        </>
      )}
    </Screen>
  );
}
