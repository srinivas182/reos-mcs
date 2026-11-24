import { router, useFocusEffect } from 'expo-router';
import { Mic, Sparkles, Square } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { loadStudio, makeContent, type Output, type SourceType, type StudioHome } from '@/lib/studio';
import { speechLanguage, useSpeech } from '@/lib/voice';
import { Button, Callout, ChoiceRow, IconButton, PillGroup, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

const SOURCES: SourceType[] = ['listing', 'sale', 'market', 'text'];

/**
 * Create a post: say it, type it, or start from a listing or a sale
 * (agent screen 165; B12.1). Choose what to make; the cost in credits shows
 * before anything is spent. Recordings stay on the phone; only the words go.
 */
export default function CreatePostScreen() {
  const { t, i18n } = useTranslation();
  const [home, setHome] = useState<StudioHome | null>(null);
  const [type, setType] = useState<SourceType>('text');
  const [pick, setPick] = useState<string | null>(null);
  const [text, setText] = useState('');
  const [city, setCity] = useState('');
  const [outputs, setOutputs] = useState<Output[]>(['post']);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadStudio().then((h) => {
        setHome(h);
        if (h && h.listings.length > 0) {
          setType('listing');
          setPick(h.listings[0]?.id ?? null);
        }
      });
    }, []),
  );
  const speech = useSpeech(speechLanguage(i18n.language), (words) => setText((current) => (current ? `${current} ${words}` : words)));
  const ready = outputs.length > 0 && ((type === 'listing' || type === 'sale') ? pick !== null : text.trim().length >= 3);

  const make = async () => {
    if (!ready || busy) return;
    setBusy(true);
    setProblem(null);
    try {
      const made = await makeContent(type === 'listing' || type === 'sale' ? { type, id: pick } : { type, text: text.trim(), city: city.trim() || null }, type === 'listing' ? outputs : outputs.filter((o) => o !== 'carousel'));
      router.push({ pathname: '/social/versions/[id]', params: { id: made.id } });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('studio.maker.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('studio.maker.purpose')}</Text>
      {home ? <Text variant="caption" tone="muted">{t('studio.maker.cost', { credits: home.cost, left: home.credits_left })}</Text> : null}

      <PillGroup<SourceType>
        label={t('studio.maker.from')}
        value={type}
        options={SOURCES.map((s) => ({ value: s, label: t(`studio.maker.sources.${s}`) }))}
        onChange={(s) => {
          setType(s);
          setPick(s === 'listing' ? (home?.listings[0]?.id ?? null) : s === 'sale' ? (home?.sales[0]?.id ?? null) : null);
        }}
      />

      {type === 'listing' ? (
        home && home.listings.length === 0 ? (
          <Callout tone="info" title={t('studio.maker.noListings')} />
        ) : (
          <>
            <SectionTitle>{t('studio.maker.chooseListing')}</SectionTitle>
            {home?.listings.map((l) => <ChoiceRow key={l.id} title={l.address} selected={pick === l.id} onPress={() => setPick(l.id)} />)}
          </>
        )
      ) : null}
      {type === 'sale' ? (
        home && home.sales.length === 0 ? (
          <Callout tone="info" title={t('studio.maker.noSales')} />
        ) : (
          <>
            <SectionTitle>{t('studio.maker.chooseSale')}</SectionTitle>
            {home?.sales.map((s) => <ChoiceRow key={s.id} title={s.property} selected={pick === s.id} onPress={() => setPick(s.id)} />)}
          </>
        )
      ) : null}
      {type === 'market' || type === 'text' ? (
        <>
          <View style={styles.row}>
            <View style={styles.flex}>
              <TextField
                label={type === 'market' ? t('studio.maker.marketText') : t('studio.maker.text')}
                value={speech.state === 'listening' ? speech.transcript : text}
                onChangeText={setText}
                placeholder={type === 'market' ? t('studio.maker.marketPlaceholder') : t('studio.maker.textPlaceholder')}
                multiline
                maxLength={1000}
              />
            </View>
            <IconButton icon={speech.state === 'listening' ? Square : Mic} label={speech.state === 'listening' ? t('ask.stop') : t('ask.voice')} onPress={() => (speech.state === 'listening' ? speech.stop() : void speech.start())} />
          </View>
          {type === 'market' ? <TextField label={t('studio.maker.city')} value={city} onChangeText={setCity} maxLength={80} /> : null}
        </>
      ) : null}

      <SectionTitle>{t('studio.maker.outputs')}</SectionTitle>
      {(['post', 'carousel', 'ad'] as const)
        .filter((o) => o !== 'carousel' || type === 'listing')
        .map((o) => (
          <ChoiceRow key={o} title={t(`studio.maker.outputsNames.${o}`)} selected={outputs.includes(o)} onPress={() => setOutputs((list) => (list.includes(o) ? list.filter((x) => x !== o) : [...list, o]))} />
        ))}
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      <Button icon={Sparkles} label={busy ? t('studio.maker.making') : t('studio.maker.make')} loading={busy} disabled={!ready} fullWidth onPress={() => void make()} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'flex-end', gap: theme.space.sm },
  flex: { flex: 1 },
}));
