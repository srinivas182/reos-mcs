import { Image } from 'expo-image';
import { router } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { ExternalLink, Share2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Share } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { areasFrom, loadPage, type PageSettings, savePage } from '@/lib/listings';
import { Button, Callout, Card, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * My digital card (B14.1, ADR 0064): the phone and email to show (only
 * what the agent adds), the areas they serve, the QR code to show or
 * print, and the card link to share.
 */
export default function CardScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [page, setPage] = useState<PageSettings | null>(null);
  const [phone, setPhone] = useState('');
  const [email, setEmail] = useState('');
  const [areas, setAreas] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void loadPage().then((p) => {
      setPage(p);
      setPhone(p?.phone ?? '');
      setEmail(p?.email ?? '');
      setAreas(p?.areas.join(', ') ?? '');
    });
  }, []);

  if (!page)
    return (
      <Screen>
        <TitleBar title={t('listings.page.cardTitle')} onBack={() => router.back()} />
      </Screen>
    );

  const save = async () => {
    setBusy(true);
    setError(null);
    try {
      const next = await savePage(page.published, page.bio, { phone: phone.trim() === '' ? null : phone.trim(), email: email.trim() === '' ? null : email.trim(), areas: areasFrom(areas) });
      setPage(next);
      toast({ message: t('listings.page.saved') });
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('listings.page.cardTitle')} onBack={() => router.back()} />
      <Text tone="muted">{t('listings.page.cardHint')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      {page.published ? (
        <Card>
          <Image source={{ uri: page.qr_url }} style={styles.qr} contentFit="contain" accessibilityLabel={t('listings.page.qrAlt')} />
          <Text tone="muted">{page.card_url}</Text>
          <Button icon={Share2} label={t('listings.post.share')} onPress={() => void Share.share({ message: page.card_url })} />
          <Button variant="secondary" icon={ExternalLink} label={t('listings.page.openCard')} onPress={() => void WebBrowser.openBrowserAsync(page.card_url)} />
        </Card>
      ) : (
        <Callout tone="info" title={t('listings.page.hidden')} />
      )}

      <Card>
        <TextField label={t('listings.page.phone')} value={phone} keyboardType="phone-pad" autoComplete="tel" maxLength={30} onChangeText={setPhone} />
        <TextField label={t('listings.page.email')} value={email} keyboardType="email-address" autoCapitalize="none" autoComplete="email" maxLength={120} onChangeText={setEmail} hint={t('listings.page.contactHint')} />
        <TextField label={t('listings.page.areas')} value={areas} maxLength={400} onChangeText={setAreas} hint={t('listings.page.areasHint')} />
        <Button label={t('listings.page.save')} loading={busy} onPress={() => void save()} />
      </Card>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  qr: { width: theme.m.buttonHeight * 5, height: theme.m.buttonHeight * 5, alignSelf: 'center', backgroundColor: theme.colors.surface },
}));
