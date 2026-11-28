import { router, useFocusEffect } from 'expo-router';
import { BadgeCheck, Handshake } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadProviders, type ProviderCard } from '@/lib/providers';
import { Button, EmptyState, Icon, ListRow, Screen, Text, TextField, TitleBar } from '@/ui';

/** Verified providers on the phone (B15.3): live providers whose licences REOS checked, by city or ZIP code. */
export default function ProvidersScreen() {
  const { t } = useTranslation();
  const [providers, setProviders] = useState<ProviderCard[] | null>(null);
  const [place, setPlace] = useState('');

  useFocusEffect(
    useCallback(() => {
      void loadProviders().then(setProviders);
    }, []),
  );

  return (
    <Screen>
      <TitleBar title={t('providers.directory.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.directory.purpose')}</Text>
      <TextField label={t('providers.directory.place')} value={place} maxLength={80} onChangeText={setPlace} />
      <Button variant="secondary" label={t('providers.directory.search')} onPress={() => void loadProviders(undefined, place.trim()).then(setProviders)} />
      {providers !== null && providers.length === 0 ? <EmptyState icon={<Icon icon={Handshake} color="action" size={32} />} title={t('providers.directory.emptyTitle')} description={t('providers.directory.empty')} /> : null}
      {providers?.map((p) => (
        <ListRow
          key={p.id}
          icon={BadgeCheck}
          tone={p.licence.verified ? 'success' : 'warning'}
          title={p.name}
          subtitle={[p.category, p.areas.join(' · ')].filter(Boolean).join('\n')}
          onPress={() => router.push({ pathname: '/providers/[id]', params: { id: String(p.id) } })}
        />
      ))}
    </Screen>
  );
}
