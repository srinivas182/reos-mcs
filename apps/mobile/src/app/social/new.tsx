import * as DocumentPicker from 'expo-document-picker';
import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { CircleCheck, CircleX, ImagePlus, Trash2, Wand2 } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { Image, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { type Channel, type CheckResult, checkPost, type Pick, type PostBody, savePost, uploadMedia, useSocialAccounts } from '@/lib/social';
import { fixPost } from '@/lib/studio';
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
  // From Pick a version (B12.1): the chosen caption and design, and the listing they came from.
  const { post: editId, listing, caption, media: studioMedia, at: plannedAt } = useLocalSearchParams<{ post?: string; listing?: string; caption?: string; media?: string; at?: string }>();
  // Plan a post from the calendar (B12.3): scheduled for that day at 10 am.
  const planned = plannedAt && /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(plannedAt) ? new Date(`${plannedAt}:00`).toISOString() : null;
  const { accounts } = useSocialAccounts();
  const [body, setBody] = useState('');
  const [media, setMedia] = useState<Media[]>([]);
  const [listingPhotos, setListingPhotos] = useState<{ url: string; caption: string | null }[]>([]);
  const [picks, setPicks] = useState<Pick[]>([]);
  const [when, setWhen] = useState<When>(planned ? 'later' : 'now');
  const [asking, setAsking] = useState(false);
  const [reason, setReason] = useState('');
  const [at, setAt] = useState<string | null>(planned);
  const [check, setCheck] = useState<CheckResult | null>(null);
  const [returned, setReturned] = useState<string | null>(null);
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
            setReturned(p.status === 'draft' ? p.approval_note : null);
          })
          .catch(() => undefined);
      } else if (caption !== undefined) {
        setBody(caption);
        try {
          const chosen = JSON.parse(studioMedia ?? '[]') as { path: string; url: string; kind?: string }[];
          setMedia(chosen.map((m) => ({ value: m.path, preview: m.url, kind: m.kind ?? 'image' })));
        } catch {
          setMedia([]);
        }
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
    }, [editId, listing, caption, studioMedia]),
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
  const submit = async (askBroker?: string) => {
    setBusy(true);
    try {
      const saved = await savePost(askBroker ? { ...request(when), ask_broker: askBroker } : request(when), editId);
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
              <Text style={styles.flex}>
                {['length', 'media', 'video'].includes(c.code) ? t(`social.checks.${c.code}`, { channel: c.detail ? t(`social.channels.${c.detail as Channel}`) : '' }) : t(`social.checks.${c.code}`, { channel: '' })}
                {!c.ok && c.detail && !['length', 'media', 'video'].includes(c.code) ? `: ${c.detail}` : ''}
                {!c.ok && c.citation ? ` (${c.citation})` : ''}
              </Text>
            </View>
          ))}
          {check.issues.length > 0 ? (
            <Button
              variant="secondary"
              icon={Wand2}
              label={t('studio.checks.fix')}
              onPress={() =>
                void fixPost(body)
                  .then(setBody)
                  .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
              }
            />
          ) : null}
          {check.review.length > 0 ? <Callout tone="warning" title={t('compliance.review', { phrases: check.review.join(', ') })} /> : null}
          {check.approval ? <Callout tone="info" title={t('studio.checks.approval')} /> : null}
        </>
      ) : null}
      {returned ? <Callout tone="warning" title={t('studio.checks.returned', { note: returned })} /> : null}
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
          options={[...(planned ? [{ value: planned }] : []), ...slots(now)].map((s) => ({ value: s.value, label: timeLabel(s.value) }))}
          onChange={setAt}
        />
      ) : null}
      {check && !check.ok && when !== 'draft' ? <Text tone="danger">{t('social.compose.fixFirst')}</Text> : null}
      {/* Routing (B13.2): flagged wording sent to the broker with a reason instead of fixed. */}
      {check && !check.ok && check.routable && when !== 'draft' ? (
        asking ? (
          <>
            <TextField label={t('compliance.askBroker.reason')} hint={t('compliance.askBroker.reasonHint')} value={reason} maxLength={500} multiline onChangeText={setReason} />
            <Button label={t('compliance.askBroker.send')} loading={busy} disabled={busy || reason.trim().length < 3} onPress={() => void submit(reason.trim())} fullWidth />
            <Button variant="ghost" label={t('compliance.askBroker.cancel')} onPress={() => setAsking(false)} fullWidth />
          </>
        ) : (
          <Button variant="secondary" label={t('compliance.askBroker.button')} onPress={() => setAsking(true)} fullWidth />
        )
      ) : null}
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
  flex: { flex: 1 },
}));
