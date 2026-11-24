import * as DocumentPicker from 'expo-document-picker';
import { router, useLocalSearchParams } from 'expo-router';
import { useVideoPlayer, VideoView } from 'expo-video';
import { ArrowDown, ArrowRight, ArrowUp, Check, ImagePlus, Sparkles, Trash2, Wand2 } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { Image, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { uploadMedia } from '@/lib/social';
import { editVideo, loadVideo, type SceneEdit, sceneEdits, type Track, type Video, type VideoEdits, videoHooks } from '@/lib/studio';
import { Button, Callout, Card, Chip, PillGroup, Screen, SectionTitle, Stepper, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Tab = 'trim' | 'captions' | 'music' | 'brand' | 'hook';
type Meta = { kind: 'image' | 'video'; length: number | null; url: string };

const TABS: Tab[] = ['trim', 'captions', 'music', 'brand', 'hook'];
const TRACKS: Track[] = ['calm', 'bright', 'none'];
const MAX_SECONDS = 60;
const STEP = 0.5;
const round = (n: number) => Math.round(n * 10) / 10;

/**
 * Edit video (agent screen 168; B12.2): a vertical preview, the timeline,
 * and Trim, Captions, Music, Brand and Hook. Done checks the words again
 * and renders the video (free); with flagged words, it shows them and
 * offers Fix automatically instead. Then: Use in a post.
 */
export default function EditVideoScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [video, setVideo] = useState<Video | null>(null);
  const [scenes, setScenes] = useState<SceneEdit[]>([]);
  const [meta, setMeta] = useState<Record<string, Meta>>({});
  const [title, setTitle] = useState('');
  const [music, setMusic] = useState<Track>('calm');
  const [brand, setBrand] = useState(true);
  const [tab, setTab] = useState<Tab>('trim');
  const [hooks, setHooks] = useState<string[]>([]);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  const take = useCallback((v: Video) => {
    setVideo(v);
    setScenes(sceneEdits(v.scenes));
    setMeta((m) => ({ ...m, ...Object.fromEntries(v.scenes.map((s) => [s.media, { kind: s.kind, length: s.length, url: s.url }])) }));
    setTitle(v.title);
    setMusic(v.music);
    setBrand(v.brand);
  }, []);

  useEffect(() => {
    void loadVideo(id).then((v) => (v ? take(v) : undefined));
  }, [id, take]);

  // While it renders, look again every few seconds.
  const rendering = video?.status === 'rendering';
  useEffect(() => {
    if (!rendering) return;
    const timer = setInterval(() => {
      void loadVideo(id).then((v) => (v && v.status !== 'rendering' ? take(v) : undefined));
    }, 3000);
    return () => clearInterval(timer);
  }, [id, rendering, take]);

  const player = useVideoPlayer(video?.status === 'ready' && video.video ? video.video.url : null, (p) => {
    p.loop = true;
  });
  const total = round(scenes.reduce((sum, s) => sum + s.seconds, 0));

  const save = async (extra: Pick<VideoEdits, 'fix' | 'render'>) => {
    setBusy(true);
    setProblem(null);
    try {
      take(await editVideo(id, { title, music, brand, scenes, ...extra }));
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const change = (i: number, patch: Partial<SceneEdit>) => setScenes((list) => list.map((s, j) => (j === i ? { ...s, ...patch } : s)));
  const move = (i: number, by: -1 | 1) =>
    setScenes((list) => {
      const next = [...list];
      const [s] = next.splice(i, 1);
      if (s) next.splice(i + by, 0, s);
      return next;
    });
  const remove = (i: number) => {
    const before = scenes;
    setScenes((list) => list.filter((_, j) => j !== i));
    toast({ message: t('studio.video.removed'), onUndo: () => setScenes(before) });
  };
  const addClip = async () => {
    const picked = await DocumentPicker.getDocumentAsync({ type: ['image/*', 'video/mp4', 'video/quicktime'], copyToCacheDirectory: true });
    const file = picked.canceled ? undefined : picked.assets[0];
    if (!file) return;
    try {
      const up = await uploadMedia({ uri: file.uri, name: file.name, type: file.mimeType ?? 'image/jpeg' });
      const kind = up.kind === 'video' ? 'video' : 'image';
      setMeta((m) => ({ ...m, [up.path]: { kind, length: null, url: up.url } }));
      setScenes((list) => [...list, { media: up.path, start: 0, seconds: 4, caption: '' }]);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };
  const suggest = async () => {
    setBusy(true);
    try {
      setHooks(await videoHooks(id));
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const use = () => {
    if (!video?.video) return;
    router.push({ pathname: '/social/new', params: { caption: title, media: JSON.stringify([{ path: video.video.path, url: video.video.url, kind: 'video' }]), ...(video.listing ? { listing: video.listing } : {}) } });
  };

  if (!video) return <Screen>{null}</Screen>;

  return (
    <Screen>
      <TitleBar title={t('studio.video.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('studio.video.purpose')}</Text>

      <View style={styles.preview} accessibilityLabel={t('studio.video.play')}>
        {video.status === 'ready' && video.video ? (
          <VideoView player={player} style={styles.media} contentFit="cover" nativeControls />
        ) : video.poster ? (
          <Image source={{ uri: video.poster.url }} style={styles.media} accessibilityIgnoresInvertColors />
        ) : null}
      </View>
      <Text variant="caption" tone="muted" accessibilityLiveRegion="polite">
        {rendering ? t('studio.video.rendering') : video.status === 'failed' ? (video.error ?? t('studio.video.failed')) : t('studio.video.ready', { seconds: video.seconds ?? total })}
      </Text>

      {video.issues.length > 0 ? (
        <>
          <Callout tone="warning" title={t('studio.video.issues')} body={video.issues.map((issue) => (issue.fix ? `“${issue.phrase}” → “${issue.fix}”` : `“${issue.phrase}”`)).join('\n')} live />
          <Button variant="secondary" icon={Wand2} label={t('studio.video.fix')} disabled={busy || rendering} onPress={() => void save({ fix: true })} />
        </>
      ) : null}

      <SectionTitle>{t('studio.video.timeline')}</SectionTitle>
      <View style={styles.timeline} accessible accessibilityLabel={`${t('studio.video.timeline')}: ${t('studio.video.total', { seconds: total })}`}>
        {scenes.map((s, i) => (
          <View key={`${s.media}-${i}`} style={[styles.block, { flex: s.seconds }, meta[s.media]?.kind === 'video' && styles.clip]} />
        ))}
      </View>
      <Text variant="caption" tone={total > MAX_SECONDS ? 'danger' : 'muted'}>
        {total > MAX_SECONDS ? t('studio.video.tooLong', { seconds: MAX_SECONDS }) : t('studio.video.total', { seconds: total })}
      </Text>

      <PillGroup<Tab> label={t('studio.video.title')} value={tab} options={TABS.map((x) => ({ value: x, label: t(`studio.video.tabs.${x}`) }))} onChange={setTab} />

      {tab === 'trim' ? (
        <>
          {scenes.map((s, i) => {
            const m = meta[s.media];
            const clip = m?.kind === 'video';
            const name = `${t('studio.video.scene', { n: i + 1 })} · ${clip ? t('studio.video.clip') : t('studio.video.photo')}`;
            const maxStart = m?.length != null ? Math.max(0, m.length - 1.5) : 600;
            return (
              <Card key={`${s.media}-${i}`} style={styles.gap}>
                <View style={styles.row}>
                  {m && !clip && m.url ? <Image source={{ uri: m.url }} style={styles.thumb} accessibilityIgnoresInvertColors /> : null}
                  <Text weight="semibold" style={styles.flex}>
                    {name}
                  </Text>
                </View>
                <Stepper
                  label={name}
                  value={t('studio.video.seconds', { seconds: s.seconds })}
                  earlier={t('studio.video.shorter')}
                  later={t('studio.video.longer')}
                  onEarlier={() => change(i, { seconds: Math.max(1.5, round(s.seconds - STEP)) })}
                  onLater={() => change(i, { seconds: Math.min(10, round(s.seconds + STEP)) })}
                />
                {clip ? (
                  <Stepper
                    label={t('studio.video.startAt', { seconds: s.start })}
                    value={t('studio.video.startAt', { seconds: s.start })}
                    earlier={t('studio.video.earlier')}
                    later={t('studio.video.later')}
                    onEarlier={() => change(i, { start: Math.max(0, round(s.start - STEP)) })}
                    onLater={() => change(i, { start: Math.min(maxStart, round(s.start + STEP)) })}
                  />
                ) : null}
                <View style={styles.wrap}>
                  {i > 0 ? <Chip icon={ArrowUp} label={t('studio.video.moveUp')} onPress={() => move(i, -1)} /> : null}
                  {i < scenes.length - 1 ? <Chip icon={ArrowDown} label={t('studio.video.moveDown')} onPress={() => move(i, 1)} /> : null}
                  {scenes.length > 1 ? <Chip icon={Trash2} label={t('studio.video.remove')} onPress={() => remove(i)} /> : null}
                </View>
              </Card>
            );
          })}
          <Text variant="caption" tone="muted">
            {t('studio.video.pauses')}
          </Text>
          <Button variant="secondary" icon={ImagePlus} label={t('studio.video.addClip')} disabled={scenes.length >= 12} onPress={() => void addClip()} />
        </>
      ) : null}

      {tab === 'captions'
        ? scenes.map((s, i) => <TextField key={`${s.media}-${i}`} label={t('studio.video.caption', { n: i + 1 })} value={s.caption} onChangeText={(caption) => change(i, { caption })} maxLength={90} />)
        : null}

      {tab === 'music' ? (
        <>
          <PillGroup<Track> label={t('studio.video.tabs.music')} value={music} options={TRACKS.map((x) => ({ value: x, label: t(`studio.video.tracks.${x}`) }))} onChange={setMusic} />
          <Text variant="caption" tone="muted">
            {t('studio.video.musicNote')}
          </Text>
        </>
      ) : null}

      {tab === 'brand' ? <SwitchRow label={t('studio.video.brandOn')} description={t('studio.video.brandNote')} value={brand} onValueChange={setBrand} /> : null}

      {tab === 'hook' ? (
        <>
          <TextField label={t('studio.video.hookLabel')} value={title} onChangeText={setTitle} maxLength={80} />
          <Button variant="secondary" icon={Sparkles} label={t('studio.video.suggest')} loading={busy} disabled={busy} onPress={() => void suggest()} />
          {hooks.length > 0 ? (
            <>
              <Text weight="semibold">{t('studio.video.suggestions')}</Text>
              <View style={styles.wrap}>
                {hooks.map((h) => (
                  <Chip key={h} tone={h === title ? 'info' : 'neutral'} label={h} onPress={() => setTitle(h)} />
                ))}
              </View>
            </>
          ) : null}
        </>
      ) : null}

      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Button icon={Check} label={t('studio.video.done')} loading={busy || rendering} disabled={busy || rendering || total > MAX_SECONDS || scenes.length === 0} fullWidth onPress={() => void save({ render: true })} />
      {video.status === 'ready' && video.video ? <Button variant="secondary" icon={ArrowRight} label={t('studio.video.use')} disabled={busy} fullWidth onPress={use} /> : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  preview: { alignSelf: 'center', width: 216, height: 384, borderRadius: theme.radius.lg, overflow: 'hidden', backgroundColor: theme.colors.surfaceMuted },
  media: { width: '100%', height: '100%' },
  timeline: { flexDirection: 'row', height: 28, gap: 2, borderRadius: theme.radius.sm, overflow: 'hidden' },
  block: { backgroundColor: theme.colors.action },
  clip: { backgroundColor: theme.colors.textMuted },
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
  flex: { flex: 1 },
  thumb: { width: 48, height: 48, borderRadius: theme.radius.sm },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
}));
