import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect } from 'expo-router';
import { Camera, Trash2 } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { Image, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type BrandKit, loadBrandKit, removeProfilePhoto, uploadProfilePhoto } from '@/lib/content-calendar';
import { Button, Callout, Card, EmptyState, Icon, MenuGroup, MenuRow, Screen, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Profile photo and brand kit (agent screen 162; B12.3), from Me → Photo
 * and brand: the photo on celebration designs (squared, lighting evened
 * out, with a quality check), name, brokerage (required), license number,
 * and the brokerage's brand color and logo (set on the web).
 */
export default function BrandKitScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [kit, setKit] = useState<BrandKit | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(useCallback(() => void loadBrandKit().then(setKit), []));

  const upload = async () => {
    const picked = await DocumentPicker.getDocumentAsync({ type: ['image/jpeg', 'image/png', 'image/webp'], copyToCacheDirectory: true });
    const file = picked.canceled ? undefined : picked.assets[0];
    if (!file) return;
    setBusy(true);
    setProblem(null);
    try {
      setKit(await uploadProfilePhoto({ uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' }));
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const remove = async () => {
    setBusy(true);
    try {
      setKit(await removeProfilePhoto());
      toast({ message: t('studio.brand.removed') });
    } finally {
      setBusy(false);
    }
  };

  if (!kit) return <Screen>{null}</Screen>;

  return (
    <Screen>
      <TitleBar title={t('studio.brand.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('studio.brand.purpose')}</Text>

      <Card style={styles.photo}>
        {kit.photo ? (
          <Image source={{ uri: kit.photo.url }} style={styles.face} accessibilityLabel={t('studio.brand.photo')} />
        ) : (
          <EmptyState icon={<Icon icon={Camera} color="action" size={32} />} title={t('studio.brand.noPhoto')} description={t('studio.brand.purpose')} />
        )}
        {kit.check ? (
          kit.check.issues.length === 0 ? (
            <Text tone="success">{t('studio.brand.good')}</Text>
          ) : (
            kit.check.issues.map((issue) => (
              <Text key={issue} tone="warning">
                {t(`studio.brand.issues.${issue}`)}
              </Text>
            ))
          )
        ) : null}
        <Button icon={Camera} label={kit.photo ? t('studio.brand.replace') : t('studio.brand.upload')} loading={busy} disabled={busy} fullWidth onPress={() => void upload()} />
        {kit.photo ? <Button variant="ghost" icon={Trash2} label={t('studio.brand.remove')} disabled={busy} fullWidth onPress={() => void remove()} /> : null}
      </Card>
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      {kit.brokerage === null ? <Callout tone="warning" title={t('studio.brand.noBrokerage')} /> : null}
      <MenuGroup>
        <MenuRow title={t('studio.brand.name')} value={kit.name} />
        <MenuRow title={t('studio.brand.brokerage')} value={kit.brokerage ?? '—'} />
        <MenuRow title={t('studio.brand.licence')} value={kit.licence ?? '—'} />
      </MenuGroup>
      <View style={styles.row} accessible accessibilityLabel={`${t('studio.brand.colour')}: ${kit.colour}`}>
        {/* The brokerage's own color, from its brand kit (data, not a theme color). */}
        <View style={[styles.swatch, { backgroundColor: kit.colour }]} />
        <Text>{t('studio.brand.colour')}</Text>
      </View>
      {kit.logo ? <Image source={{ uri: kit.logo }} style={styles.logo} resizeMode="contain" accessibilityLabel={t('studio.brand.logo')} /> : null}
      <Text variant="caption" tone="muted">
        {t('studio.brand.kitNote')}
      </Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  photo: { alignItems: 'center', gap: theme.space.md },
  face: { width: 160, height: 160, borderRadius: 80 },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  swatch: { width: theme.m.buttonHeight, height: theme.m.buttonHeight, borderRadius: theme.radius.md, borderWidth: 1, borderColor: theme.colors.border },
  logo: { width: 160, height: 64 },
}));
