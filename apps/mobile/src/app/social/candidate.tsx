import { router, useFocusEffect } from 'expo-router';
import { PartyPopper, Send, Share2, ShieldCheck } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Image, Pressable, Share, View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { checkCaption, type CheckResult, loadMoments, type MadePost, makePost, type Moment, postIt } from '@/lib/candidate-studio';
import { useSocialAccounts } from '@/lib/social';
import { Button, Callout, Card, EmptyState, Icon, ListRow, PillGroup, Screen, SectionTitle, Text, TextField, TitleBar, useUndoToast } from '@/ui';

type Tone = 'professional' | 'warm' | 'short';

/**
 * The candidate studio (B13.3; candidate study wins): free posts for a
 * study streak, a practice score and passing the exam, with the school's or
 * REOS's brand and "Future agent". Every caption is checked: no service
 * advertising before the license is active.
 */
export default function CandidateStudioScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const { accounts } = useSocialAccounts();
  const [moments, setMoments] = useState<Moment[] | null>(null);
  const [made, setMade] = useState<MadePost | null>(null);
  const [caption, setCaption] = useState(0);
  const [design, setDesign] = useState(0);
  const [text, setText] = useState('');
  const [result, setResult] = useState<CheckResult | null>(null);
  const [busy, setBusy] = useState(false);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadMoments().then(setMoments);
    }, []),
  );

  const targets = (accounts ?? []).flatMap((a) => (a.connection ? a.targets.map((target) => ({ connection: a.connection?.id ?? 0, channel: target.channel, id: target.id })) : []));
  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    setProblem(null);
    try {
      await work();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    } finally {
      setBusy(false);
    }
  };
  const open = (m: Moment) =>
    void run(async () => {
      const post = await makePost(m.milestone);
      setMade(post);
      setCaption(0);
      setDesign(0);
      setText(post.captions[0]?.text ?? '');
      setResult(null);
    });

  return (
    <Screen>
      <TitleBar title={t('studio.candidate.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('studio.candidate.purpose')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      <SectionTitle>{t('studio.candidate.moments')}</SectionTitle>
      {moments !== null && moments.length === 0 ? <EmptyState icon={<Icon icon={PartyPopper} color="action" size={32} />} title={t('studio.candidate.none')} description={t('studio.candidate.noneHint')} /> : null}
      {moments?.map((m) => (
        <ListRow key={m.milestone} icon={PartyPopper} tone="info" title={m.label} subtitle={m.made ? t('studio.candidate.open') : t('studio.candidate.make')} onPress={() => open(m)} />
      ))}

      {made ? (
        <Card style={styles.gap}>
          <Text weight="semibold">{made.label}</Text>
          <Text variant="caption" tone="muted">
            {t('studio.candidate.rule')}
          </Text>
          <View style={styles.row}>
            {made.designs.map((d, i) => (
              <Pressable key={d.path} accessibilityRole="button" accessibilityLabel={t('studio.candidate.design', { n: i + 1 })} accessibilityState={{ selected: i === design }} onPress={() => setDesign(i)}>
                {d.url ? <Image source={{ uri: d.url }} style={[styles.thumb, i === design ? styles.picked : null]} /> : <View style={[styles.thumb, i === design ? styles.picked : null]} />}
              </Pressable>
            ))}
          </View>
          <PillGroup<Tone>
            label={t('studio.candidate.captions')}
            value={made.captions[caption]?.tone ?? 'professional'}
            options={made.captions.map((c) => ({ value: c.tone, label: t(`studio.maker.tones.${c.tone}`) }))}
            onChange={(tone) => {
              const i = Math.max(0, made.captions.findIndex((c) => c.tone === tone));
              setCaption(i);
              setText(made.captions[i]?.text ?? '');
              setResult(null);
            }}
          />
          <TextField
            label={t('studio.candidate.edit')}
            value={text}
            maxLength={2000}
            multiline
            onChangeText={(v) => {
              setText(v);
              setResult(null);
            }}
          />
          {result?.ok ? <Callout tone="success" title={t('studio.candidate.ok')} live /> : null}
          {result && !result.ok ? <Callout tone="danger" title={result.issues.map((i) => t('compliance.blocked.unlicensed', { phrase: i.phrase ?? '', citation: i.citation })).join('\n')} live /> : null}
          <Button variant="secondary" icon={ShieldCheck} label={t('studio.candidate.check')} loading={busy} onPress={() => void run(async () => setResult(await checkCaption(text)))} />
          {targets.length > 0 ? (
            <Button
              icon={Send}
              label={t('studio.candidate.post')}
              loading={busy}
              disabled={result?.ok === false}
              onPress={() =>
                void run(async () => {
                  await postIt(made.id, caption, design, text, targets);
                  toast({ message: t('studio.candidate.posted') });
                })
              }
            />
          ) : (
            <Text variant="caption" tone="muted">
              {t('studio.candidate.noAccounts')}
            </Text>
          )}
          <Button
            variant="secondary"
            icon={Share2}
            label={t('studio.candidate.share')}
            disabled={result?.ok === false}
            onPress={() =>
              void run(async () => {
                const checked = await checkCaption(text);
                setResult(checked);
                if (checked.ok) await Share.share({ message: text, url: made.designs[design]?.url || undefined });
              })
            }
          />
        </Card>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap' },
  thumb: { width: theme.m.buttonHeight * 2, height: theme.m.buttonHeight * 2, borderRadius: theme.radius.md, borderWidth: 2, borderColor: theme.colors.border, backgroundColor: theme.colors.actionTint },
  picked: { borderColor: theme.colors.action },
}));
