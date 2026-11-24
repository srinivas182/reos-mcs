import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { CircleCheck, CircleX, ImagePlus, Trash2 } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { Image, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { type Channel, type CheckResult, checkPost, type Pick, type PostBody, savePost, uploadMedia, useSocialAccounts } from '@/lib/social';
import { Button, Callout, Card, Icon, ListRow, PillGroup, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Media = { value: string; preview: string; kind: string };
type When = 'now' | 'later' | 'draft';

/** Times to schedule for, a quarter-hour apart over the next week, as pills of the most useful ones. */
function slots(now: Date): { value: string; label: string }[] {
  const out: { value: string; label: string }[] = [];
  const start = new Date(now);
  start.setMinutes(0, 0, 0);
  for (const add of [2, 4, 24, 26, 48, 72]) {
    const at = new Date(start.getTime() + add * 3600_000);
    out.push({ value: at.toISOString(), label: at.toISOString() });
  }
  return out;
}

/**
 * Publish or schedule a post (B11; agent screen 108): the text, a photo or
 * video (or the listing's photos), the accounts with switches, the preview
 * with the broker name and the checks, then Now, a time or a draft. A post
 * that fails a check can't be scheduled until it's fixed.
 */
export default function NewPostScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { post: editId, listing } = useLocalSearchParams<{ post?: string; listing?: string }>();
  const { accounts } = useSocialAccounts();
  const [body, setBody] = useState('');
  const [media, setMedia] = useState<Media[]>([]);
  const [listingPhotos, setListingPhotos] = useState<{ url: string; caption: string | null }[]>([]);
  const [picks, setPicks] = useState<Pick[]>([]);
  const [when, setWhen] = useState<When>('now');
  const [at, setAt] = useState<string | null>(null);
  const [check, setCheck] = useState<CheckResult | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [now] = useState(() => new Date());

  useFocusEffect(
    useCallback(() => {
      if (editId) {
        void api()
          .GET('/social/posts/{post}', { params: { path: { post: editId } } })
          .then(({ data }) => {
            const p = data?.data;
            if (!p) return;
            setBody(p.body);
            setMedia(p.media.map((m) => ({ value: m.url, preview: m.url, kind: m.kind })));
            setWhen(p.status === 'scheduled' ? 'later' : 'draft');
            setAt(p.scheduled_at);
          })
          .catch(() => undefined);
      } else if (listing) {
        void api()
          .GET('/listings/{listing}/post', { params: { path: { listing } } })
          .then(({ data }) => {
            const d = data?.data;
            if (!d) return;
            setBody(d.caption);
            setListingPhotos(d.photos);
            setMedia(d.chosen.map((url) => ({ value: url, preview: url, kind: 'image' })));
          })
          .catch(() => undefined);
      }
    }, [editId, listing]),
  );

  const request = useCallback(
    (whenValue: When): PostBody => ({ body, media: media.map((m) => m.value), link: null, targets: picks, when: whenValue, at: whenValue === 'later' ? at : null, listing: listing ?? null }),
    [body, media, picks, at, listing],
  );

  useEffect(() => {
    const timer = setTimeout(() => {
      void checkPost(request('draft'))
        .then(setCheck)
        .catch(() => undefined);
    }, 400);
    return () => clearTimeout(timer);
  }, [request]);

  if (accounts === null) return <Screen>{null}</Screen>;

  const targets = accounts.flatMap((a) => (a.connection ? a.targets.map((target) => ({ connection: a.connection?.id ?? 0, target })) : []));
  const isPicked = (p: Pick) => picks.some((q) => q.connection === p.connection && q.channel === p.channel && q.id === p.id);
  const pickMedia = async () => {
    const picked = await DocumentPicker.getDocumentAsync({ type: ['image/*', 'video/mp4', 'video/quicktime'], copyToCacheDirectory: true });
    const file = picked.canceled ? undefined : picked.assets[0];
    if (!file) return;
    try {
      const up = await uploadMedia({ uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' });
      setMedia((m) => [...m, { value: up.path, preview: up.url, kind: up.kind }]);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };
  const submit = async () => {
    setBusy(true);
    try {
      const saved = await savePost(request(when), editId);
      setProblem(null);
      toast({
        message:
          when === 'now'
            ? t('social.compose.published')
            : when === 'later' && saved.scheduled_at
              ? t('social.compose.scheduled', { date: new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(saved.scheduled_at)) })
              : t('social.compose.saved'),
      });
      router.replace('/social');
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const label = when === 'now' ? t('social.compose.publish') : when === 'later' ? t('social.compose.schedule') : t('social.compose.saveDraft');
  const timeLabel = (iso: string) => new Intl.DateTimeFormat(i18n.language, { weekday: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(iso));

  return (
    <Screen>
      <TitleBar title={t('social.compose.title')} onBack={() => router.back()} />
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <TextField label={t('social.compose.text')} value={body} onChangeText={setBody} multiline />
      <SectionTitle>{t('social.compose.media')}</SectionTitle>
      {listing
        ? listingPhotos.map((photo) => {
            const on = media.some((m) => m.value === photo.url);
            return (
              <SwitchRow
                key={photo.url}
                label={photo.caption ?? photo.url.split('/').pop() ?? photo.url}
                value={on}
                onValueChange={(next) => setMedia((m) => (next ? [...m, { value: photo.url, preview: photo.url, kind: 'image' }] : m.filter((x) => x.value !== photo.url)))}
              />
            );
          })
        : null}
      {!listing ? (
        <>
          <View style={styles.media}>
            {media.map((m) => (
              <View key={m.value} style={styles.thumb}>
                {m.kind === 'image' ? <Image source={{ uri: m.preview }} style={styles.image} accessibilityIgnoresInvertColors /> : <Text>{t('social.compose.media')}</Text>}
                <Button variant="secondary" icon={Trash2} label={t('social.compose.removeMedia')} onPress={() => setMedia((all) => all.filter((x) => x.value !== m.value))} />
              </View>
            ))}
          </View>
          <Button variant="secondary" icon={ImagePlus} label={t('social.compose.addMedia')} onPress={() => void pickMedia()} />
        </>
      ) : null}
      <SectionTitle>{t('social.compose.chooseAccounts')}</SectionTitle>
      {targets.length === 0 ? <ListRow icon={ImagePlus} tone="info" title={t('social.accounts.emptyTitle')} subtitle={t('social.accounts.emptyBody')} onPress={() => router.push('/social/accounts')} /> : null}
      {targets.map(({ connection, target }) => {
        const pick: Pick = { connection, channel: target.channel, id: target.id };
        return (
          <SwitchRow
            key={`${connection}-${target.channel}-${target.id}`}
            label={`${t(`social.channels.${target.channel as Channel}`)} · ${target.name}`}
            value={isPicked(pick)}
            onValueChange={(on) => setPicks((all) => (on ? [...all, pick] : all.filter((q) => !(q.connection === connection && q.channel === target.channel && q.id === target.id))))}
          />
        );
      })}
      <Card style={styles.preview}>
        <Text variant="title">{t('social.compose.preview')}</Text>
        <Text>{body}</Text>
        {check ? <Text tone="muted">{check.attribution}</Text> : null}
      </Card>
      {check ? (
        <>
          <SectionTitle>{t('social.compose.checks')}</SectionTitle>
          {check.checks.map((c, i) => (
            <View key={`${c.code}-${c.detail ?? i}`} style={styles.check}>
              <Icon icon={c.ok ? CircleCheck : CircleX} color={c.ok ? 'success' : 'danger'} size={20} />
              <Text>{t(`social.checks.${c.code}`, { channel: c.detail ? t(`social.channels.${c.detail as Channel}`) : '' })}</Text>
            </View>
          ))}
        </>
      ) : null}
      <PillGroup<When>
        label={t('social.compose.when')}
        value={when}
        options={(['now', 'later', 'draft'] as const).map((w) => ({ value: w, label: t(`social.compose.${w}`) }))}
        onChange={setWhen}
      />
      {when === 'later' ? (
        <PillGroup<string>
          label={t('social.compose.at')}
          value={at ?? ''}
          options={slots(now).map((s) => ({ value: s.value, label: timeLabel(s.value) }))}
          onChange={setAt}
        />
      ) : null}
      {check && !check.ok && when !== 'draft' ? <Text tone="danger">{t('social.compose.fixFirst')}</Text> : null}
      <Button label={label} loading={busy} disabled={busy || (when !== 'draft' && check?.ok !== true) || (when === 'later' && !at)} onPress={() => void submit()} fullWidth />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  media: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  thumb: { gap: theme.space.xs },
  image: { width: theme.m.buttonHeight * 2, height: theme.m.buttonHeight * 2, borderRadius: theme.m.radius.card },
  preview: { gap: theme.space.xs },
  check: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
}));
