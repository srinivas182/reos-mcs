import { Image } from 'expo-image';
import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { BadgeCheck, Clock, Handshake, MapPin, ShieldAlert, Star } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { connectWithProvider, loadProvider, loadProviderNetwork, ratingText, reviewProvider, type ProviderCard, type ProviderNetworkInfo } from '@/lib/providers';
import { Button, Callout, Card, ListRow, PillGroup, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

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
  const [network, setNetwork] = useState<ProviderNetworkInfo | null>(null);
  const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);
  const [busy, setBusy] = useState(false);
  const [stars, setStars] = useState('5');
  const [note, setNote] = useState('');

  useEffect(() => {
    void loadProvider(Number(id)).then((p) => {
      setProvider(p);
      setMissing(p === null);
    });
    void loadProviderNetwork(Number(id)).then(setNetwork);
  }, [id]);

  // The second tap: connected at once, with the chat open in Messages (B16.3).
  const connect = () => {
    if (!provider) return;
    setBusy(true);
    connectWithProvider(provider.id)
      .then((c) => {
        setNetwork((n) => (n ? { ...n, connection: { id: c.id, status: 'accepted' }, can_connect: false } : n));
        setNotice({ tone: 'success', text: t('providers.directory.connect.connected', { name: provider.name }) });
      })
      .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
      .finally(() => setBusy(false));
  };
  const review = () => {
    if (!provider) return;
    reviewProvider(provider.id, Number(stars), note)
      .then((n) => {
        setNetwork(n);
        setNotice({ tone: 'success', text: t('providers.directory.reviews.saved') });
      })
      .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }));
  };

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
          {notice ? <Callout tone={notice.tone} title={notice.text} /> : null}
          {network?.can_connect ? <Button icon={Handshake} label={t('providers.directory.connect.button')} accessibilityLabel={t('providers.directory.connect.buttonFor', { name: provider.name })} loading={busy} onPress={connect} /> : null}
          {network?.connection?.status === 'accepted' ? (
            <ListRow
              icon={Handshake}
              tone="success"
              title={t('providers.directory.connect.already')}
              subtitle={t('providers.directory.connect.open')}
              onPress={() => router.push({ pathname: '/provider-connection/[id]', params: { id: network.connection?.id ?? '' } })}
            />
          ) : null}
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
          {network ? (
            <>
              <SectionTitle>{t('providers.directory.reviews.title')}</SectionTitle>
              <Text tone="muted">{ratingText(network.rating) ?? t('providers.directory.reviews.noneHint')}</Text>
              {network.reviews.map((r) => (
                <ListRow
                  key={r.id}
                  icon={Star}
                  title={t('providers.directory.reviews.stars', { count: r.rating })}
                  subtitle={[r.body, t('providers.directory.reviews.by', { agent: r.agent, date: new Date(r.at).toLocaleDateString(i18n.language) })].filter(Boolean).join('\n')}
                />
              ))}
              {network.can_review ? (
                <>
                  <PillGroup label={t('providers.directory.reviews.rating')} value={stars} options={['1', '2', '3', '4', '5'].map((n) => ({ value: n, label: t('providers.directory.reviews.stars', { count: Number(n) }) }))} onChange={setStars} />
                  <TextField label={t('providers.directory.reviews.body')} hint={t('providers.directory.reviews.bodyHint')} value={note} onChangeText={setNote} maxLength={1000} multiline />
                  <Button variant="secondary" label={network.my_review ? t('providers.directory.reviews.edit') : t('providers.directory.reviews.submit')} onPress={review} />
                </>
              ) : network.review_from && network.connection?.status === 'accepted' ? (
                <Text tone="muted">{t('providers.directory.reviews.from', { date: new Date(`${network.review_from}T12:00:00`).toLocaleDateString(i18n.language) })}</Text>
              ) : null}
            </>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  photo: { width: theme.m.avatar * 2, height: theme.m.avatar * 2, borderRadius: theme.m.radius.pill },
}));
