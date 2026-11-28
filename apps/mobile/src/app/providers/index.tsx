import { router, useFocusEffect } from 'expo-router';
import { BadgeCheck, Handshake, MapPin } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadNearYou, loadProviders, ratingText, type NearYou, type ProviderCard } from '@/lib/providers';
import { Button, EmptyState, Icon, ListRow, PillGroup, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/** Verified providers on the phone (B15.3): live providers whose licences REOS checked, by city or ZIP code. */
export default function ProvidersScreen() {
  const { t } = useTranslation();
  const [providers, setProviders] = useState<ProviderCard[] | null>(null);
  const [place, setPlace] = useState('');
  const [near, setNear] = useState<NearYou | null>(null);
  const [category, setCategory] = useState('all');

  useFocusEffect(
    useCallback(() => {
      void loadProviders().then(setProviders);
      void loadNearYou(category === 'all' ? null : category).then(setNear);
    }, [category]),
  );

  return (
    <Screen>
      <TitleBar title={t('providers.directory.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('providers.directory.purpose')}</Text>
      {/* Providers near you (B16.3): one tap opens a provider, the second is Request to connect. */}
      {near ? (
        <>
          <SectionTitle>{t('providers.directory.near.title')}</SectionTitle>
          <Text tone="muted">{near.cities.length > 0 ? t('providers.directory.near.serving', { cities: near.cities.join(', ') }) : t('providers.directory.near.none')}</Text>
          <PillGroup
            label={t('providers.directory.category')}
            value={category}
            options={[
              { value: 'all', label: t('providers.directory.near.all') },
              ...near.categories.filter((c) => c.count > 0 || c.key === category).map((c) => ({ value: c.key, label: t('providers.directory.near.category', { name: c.name, count: c.count }) })),
            ]}
            onChange={setCategory}
          />
          {near.providers.length === 0 ? <Text tone="muted">{t('providers.directory.near.emptyHint')}</Text> : null}
          {near.providers.map((p) => (
            <ListRow
              key={`near-${p.id}`}
              icon={p.near ? MapPin : BadgeCheck}
              tone={p.near ? 'success' : 'info'}
              title={p.name}
              subtitle={[p.category, p.near ? t('providers.directory.near.badge') : p.areas.join(', '), ratingText(p.rating)].filter(Boolean).join(' · ')}
              onPress={() => router.push({ pathname: '/providers/[id]', params: { id: String(p.id) } })}
            />
          ))}
          <SectionTitle>{t('providers.directory.search')}</SectionTitle>
        </>
      ) : null}
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
