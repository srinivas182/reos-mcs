import { Image } from 'expo-image';
import { router, useLocalSearchParams } from 'expo-router';
import { Check, Megaphone, Share2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Pressable, Share, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { loadPost, savePost, type PostDraft } from '@/lib/listings';
import { Button, Callout, Card, Icon, Screen, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * A listing post on the phone (B10.2): pick photos (never more than the MLS
 * allows), write the caption; the MLS's attribution is always added. Save
 * it, or share it now through the phone's share sheet.
 */
export default function ListingPostScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const toast = useUndoToast();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [draft, setDraft] = useState<PostDraft | null | undefined>(undefined);
  const [caption, setCaption] = useState('');
  const [chosen, setChosen] = useState<string[]>([]);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void loadPost(uuid)
      .then((found) => {
        setDraft(found);
        setCaption(found.caption);
        setChosen(found.chosen);
      })
      .catch((e: unknown) => {
        setDraft(null);
        setProblem(e instanceof Error ? e.message : String(e));
      });
  }, [uuid]);

  const toggle = (url: string) =>
    setChosen((current) => (current.includes(url) ? current.filter((u) => u !== url) : current.length >= (draft?.max_photos ?? 0) ? current : [...current, url]));

  const save = async () => {
    setBusy(true);
    try {
      const saved = await savePost(uuid, caption, chosen);
      setDraft(saved);
      setProblem(null);
      toast({ message: t('listings.post.saved') });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('listings.post.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('listings.post.description')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {draft === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : draft === null ? null : (
        <>
          <Text weight="semibold">{t('listings.post.photos')}</Text>
          <Text tone="muted">{t('listings.post.photosHint', { max: draft.max_photos, chosen: chosen.length })}</Text>
          <View style={styles.grid}>
            {draft.photos.map((photo, i) => {
              const on = chosen.includes(photo.url);

              return (
                <Pressable
                  key={photo.url}
                  accessibilityRole="checkbox"
                  accessibilityState={{ checked: on }}
                  accessibilityLabel={t('listings.post.photoAlt', { n: i + 1, address: draft.address })}
                  onPress={() => toggle(photo.url)}
                  style={[styles.photo, on ? styles.chosen : undefined]}
                >
                  <Image source={{ uri: photo.url }} style={styles.image} contentFit="cover" accessibilityIgnoresInvertColors />
                  {on ? (
                    <View style={styles.tick}>
                      <Icon icon={Check} color="onAction" size={16} />
                    </View>
                  ) : null}
                </Pressable>
              );
            })}
          </View>
          <TextField label={t('listings.post.caption')} value={caption} onChangeText={setCaption} multiline maxLength={1800} />
          <Card>
            <Text weight="semibold">{t('listings.post.attributionLabel')}</Text>
            <Text>{draft.attribution}</Text>
          </Card>
          <Text variant="caption" tone="muted">
            {t('listings.post.channelsNext')}
          </Text>
          <Button label={t('listings.post.save')} loading={busy} disabled={busy || caption.trim() === ''} onPress={() => void save()} />
          <Button
            variant="secondary"
            icon={Megaphone}
            label={t('listings.post.publish')}
            disabled={busy || caption.trim() === ''}
            onPress={() => void save().then(() => router.push({ pathname: '/social/new', params: { listing: uuid } }))}
          />
          <Button
            variant="secondary"
            icon={Share2}
            label={t('listings.post.share')}
            onPress={() => void Share.share({ message: `${caption.trim()}\n\n${draft.attribution}` })}
          />
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  photo: { width: '47%', flexGrow: 1, borderRadius: theme.m.radius.card, overflow: 'hidden', borderWidth: 3, borderColor: 'transparent' },
  chosen: { borderColor: theme.colors.action },
  image: { width: '100%', aspectRatio: 4 / 3 },
  tick: { position: 'absolute', top: theme.space.xs, right: theme.space.xs, backgroundColor: theme.colors.action, borderRadius: theme.m.radius.pill, padding: theme.space.xs },
}));
