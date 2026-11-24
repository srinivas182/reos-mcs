import { router, useLocalSearchParams } from 'expo-router';
import { ArrowRight, Briefcase, Languages, Minimize2, PartyPopper, Share2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Image, Pressable, ScrollView, Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { Celebration } from '@/lib/content-calendar';
import { type Generation, loadGeneration, type Retone, retoneCaption } from '@/lib/studio';
import { Button, Callout, Card, Chip, Screen, SectionTitle, Text, TitleBar } from '@/ui';

type Milestone = Celebration['milestone'];

/**
 * Pick a version (agent screen 166; ad studio 107): designs to swipe, three
 * captions with Shorter, More professional and Español (free), carousel
 * slides and ad copy to share. Next: share puts the chosen design and
 * caption in the post, where the checks run again before anything goes.
 */
export default function VersionsScreen() {
  const { t } = useTranslation();
  // A celebration moment (B12.3, screen 163) opens here with its milestone's badge.
  const { id, milestone } = useLocalSearchParams<{ id: string; milestone?: Milestone }>();
  const [generation, setGeneration] = useState<Generation | null>(null);
  const [design, setDesign] = useState(0);
  const [caption, setCaption] = useState(0);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useEffect(() => {
    void loadGeneration(id).then(setGeneration);
  }, [id]);

  const retone = (index: number, tone: Retone) => {
    setBusy(true);
    void retoneCaption(id, index, tone)
      .then(setGeneration)
      .catch((e: unknown) => setProblem(e instanceof Error ? e.message : String(e)))
      .finally(() => setBusy(false));
  };
  const next = () => {
    if (!generation) return;
    const chosen = generation.designs[design];
    const media = chosen ? [chosen] : generation.carousel;
    router.push({ pathname: '/social/new', params: { caption: generation.captions[caption]?.text ?? '', media: JSON.stringify(media.map((m) => ({ path: m.path, url: m.url }))), ...(generation.listing ? { listing: generation.listing } : {}) } });
  };

  return (
    <Screen>
      <TitleBar title={milestone ? t('studio.celebrate.title') : t('studio.maker.versions')} onBack={() => router.back()} />
      {generation ? (
        <>
          {milestone ? (
            <View style={styles.wrap}>
              <Chip icon={PartyPopper} tone="success" label={t(`studio.celebrate.names.${milestone}`)} />
            </View>
          ) : null}
          <Text variant="caption" tone="muted">
            {milestone ? t('studio.celebrate.free') : t('studio.maker.checked')}
          </Text>
          {generation.designs.length > 0 ? (
            <>
              <SectionTitle>{t('studio.maker.designs')}</SectionTitle>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.strip}>
                {generation.designs.map((d, i) => (
                  <Pressable key={d.path} accessibilityRole="button" accessibilityState={{ selected: design === i }} accessibilityLabel={t('studio.maker.design', { n: i + 1 })} onPress={() => setDesign(i)} style={[styles.design, design === i && styles.chosen]}>
                    <Image source={{ uri: d.url }} style={styles.image} accessibilityIgnoresInvertColors />
                  </Pressable>
                ))}
              </ScrollView>
            </>
          ) : null}

          <SectionTitle>{t('studio.maker.captions')}</SectionTitle>
          {generation.captions.map((c, i) => (
            <Card key={c.tone} onPress={() => setCaption(i)} accessibilityLabel={`${t(`studio.maker.tones.${c.tone}`)}. ${c.text}`} style={[styles.gap, caption === i && styles.chosen]}>
              <Chip tone={caption === i ? 'info' : 'neutral'} label={t(`studio.maker.tones.${c.tone}`)} />
              <Text>{c.text}</Text>
              <View style={styles.wrap}>
                <Chip icon={Minimize2} label={t('studio.maker.shorter')} onPress={busy ? undefined : () => retone(i, 'shorter')} />
                <Chip icon={Briefcase} label={t('studio.maker.professional')} onPress={busy ? undefined : () => retone(i, 'professional')} />
                <Chip icon={Languages} label={t('studio.maker.spanish')} onPress={busy ? undefined : () => retone(i, 'spanish')} />
              </View>
            </Card>
          ))}

          {generation.carousel.length > 0 ? (
            <>
              <SectionTitle>{t('studio.maker.carousel')}</SectionTitle>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={styles.strip}>
                {generation.carousel.map((s, i) => (
                  <Image key={s.path} source={{ uri: s.url }} style={styles.slide} accessibilityLabel={t('studio.maker.slide', { n: i + 1 })} />
                ))}
              </ScrollView>
            </>
          ) : null}

          {generation.ad ? (
            <>
              <SectionTitle>{t('studio.maker.ad')}</SectionTitle>
              <Card style={styles.gap}>
                <Text weight="semibold">{t('studio.maker.headlines')}</Text>
                {generation.ad.headlines.map((h) => (
                  <Text key={h}>{h}</Text>
                ))}
                <Text weight="semibold">{t('studio.maker.primary')}</Text>
                {generation.ad.primary.map((p) => (
                  <Text key={p}>{p}</Text>
                ))}
                <Text weight="semibold">{t('studio.maker.description')}</Text>
                <Text>{generation.ad.description}</Text>
                <Button variant="secondary" icon={Share2} label={t('studio.maker.copy')} onPress={() => void Share.share({ message: [...(generation.ad?.headlines ?? []), ...(generation.ad?.primary ?? []), generation.ad?.description ?? ''].join('\n') })} />
              </Card>
            </>
          ) : null}

          {problem ? <Callout tone="danger" title={problem} live /> : null}
          <Button icon={ArrowRight} label={t('studio.maker.use')} fullWidth disabled={busy} onPress={next} />
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  wrap: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  strip: { gap: theme.space.md, paddingVertical: theme.space.xs },
  design: { borderRadius: theme.radius.lg, borderWidth: 3, borderColor: 'transparent', overflow: 'hidden' },
  chosen: { borderColor: theme.colors.action },
  image: { width: 220, height: 220 },
  slide: { width: 140, height: 140, borderRadius: theme.radius.md },
}));
