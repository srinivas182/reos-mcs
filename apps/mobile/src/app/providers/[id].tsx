import { Image } from 'expo-image';
import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { BadgeCheck, Clock, MapPin, ShieldAlert } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { loadProvider, type ProviderCard } from '@/lib/providers';
import { Callout, Card, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const KINDS = ['nmls', 'trec_inspector', 'tdi', 'business_id'] as const;

/**
 * A verified provider on the phone (B15.3): the licence badge and when it
 * was checked, the bio, areas, services, response-time goal, free
 * resources and expert sessions.
 */
export default function ProviderScreen() {
  const { t, i18n } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [provider, setProvider] = useState<ProviderCard | null>(null);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    void loadProvider(Number(id)).then((p) => {
      setProvider(p);
      setMissing(p === null);
    });
  }, [id]);

  const kind = KINDS.find((k) => k === (provider?.licence.kind === 'business' ? 'business_id' : provider?.licence.kind)) ?? 'business_id';
  const checked = provider?.licence.checked_at ? new Date(provider.licence.checked_at).toLocaleDateString(i18n.language) : null;

  return (
    <Screen>
      <TitleBar title={provider?.name ?? t('providers.directory.title')} onBack={() => router.back()} />
      {missing ? <Callout tone="info" title={t('providers.directory.emptyTitle')} /> : null}
      {provider ? (
        <>
          {provider.photo_url ? <Image source={{ uri: provider.photo_url }} style={styles.photo} accessibilityIgnoresInvertColors contentFit="cover" /> : null}
          {provider.category ? <Text tone="muted">{provider.category}</Text> : null}
          <ListRow
            icon={provider.licence.verified ? BadgeCheck : ShieldAlert}
            tone={provider.licence.verified ? 'success' : 'warning'}
            title={provider.licence.verified ? t('providers.directory.verified', { kind: t(`providers.licences.kinds.${kind}`) }) : t('providers.directory.notVerified')}
            subtitle={[provider.licence.number ? t('providers.directory.licenceNumber', { number: provider.licence.number }) : null, checked ? t('providers.directory.checked', { date: checked }) : null].filter(Boolean).join(' · ')}
          />
          {provider.bio ? (
            <Card>
              <Text>{provider.bio}</Text>
            </Card>
          ) : null}
          {provider.areas.length > 0 ? <ListRow icon={MapPin} title={t('providers.directory.areas')} subtitle={provider.areas.join(' · ')} /> : null}
          {provider.response_hours ? <ListRow icon={Clock} title={t('providers.directory.response')} subtitle={t('providers.profile.responseHours', { count: provider.response_hours })} /> : null}
          {provider.services.length > 0 ? (
            <>
              <SectionTitle>{t('providers.directory.services')}</SectionTitle>
              <Text>{provider.services.join(' · ')}</Text>
            </>
          ) : null}
          {provider.specialities.length > 0 ? (
            <>
              <SectionTitle>{t('providers.directory.specialities')}</SectionTitle>
              <Text>{provider.specialities.join(' · ')}</Text>
            </>
          ) : null}
          {provider.resources.length > 0 ? <SectionTitle>{t('providers.directory.resources')}</SectionTitle> : null}
          {provider.resources.map((r) => (
            <ListRow key={r.url} icon={BadgeCheck} title={r.title} onPress={() => void WebBrowser.openBrowserAsync(r.url)} />
          ))}
          {provider.sessions.length > 0 ? <SectionTitle>{t('providers.directory.sessions')}</SectionTitle> : null}
          {provider.sessions.map((s) => (
            <Text key={s.title}>{`${s.title} · ${t('providers.profile.sessionLength', { minutes: s.minutes })}`}</Text>
          ))}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  photo: { width: theme.m.avatar * 2, height: theme.m.avatar * 2, borderRadius: theme.m.radius.pill },
}));
