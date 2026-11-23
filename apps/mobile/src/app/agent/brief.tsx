import * as Speech from 'expo-speech';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { router } from 'expo-router';
import { BookOpen, CalendarCheck, Handshake, Mic, Pause, Phone, Play, SkipForward } from 'lucide-react-native';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { BRIEF_SECTIONS, BRIEF_TIMES, type BriefCommand, type BriefSection, matchCommand, saveBriefSettings, useBrief } from '@/lib/agent';
import { voiceLanguage } from '@/lib/handsFree';
import { Button, Callout, Card, PillGroup, Screen, SectionTitle, SwitchRow, Text, TitleBar, useUndoToast } from '@/ui';

const ICONS = { call: Phone, deal: Handshake, today: CalendarCheck };

/**
 * The morning brief on the phone (A11.1c; agent screen 184): about a
 * minute read aloud through the speaker or headphones, with the transcript
 * on screen. Play, pause, skip; at the end, say a command ("Call Ana",
 * "Open the Oak Ridge deal") or tap one; or read instead. Opened from the
 * notification at the chosen time or "Hey Siri/Google, play my REOS brief".
 * Speech is recognised on the phone only.
 */
export default function BriefScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { brief, settings, setSettings } = useBrief();
  const [line, setLine] = useState<number | null>(null);
  const [playing, setPlaying] = useState(false);
  const [listening, setListening] = useState(false);
  const [heard, setHeard] = useState<string | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  // Speech callbacks outlive renders: which run may carry on, and where it is.
  const live = useRef({ run: 0, at: 0 });
  const language = voiceLanguage(i18n.language);

  useEffect(
    () => () => {
      live.current.run += 1;
      void Speech.stop();
    },
    [],
  );

  const run = useCallback(
    (command: BriefCommand) => {
      if (command.kind === 'call' && command.phone) void Linking.openURL(`tel:${command.phone.replace(/[^\d+]/g, '')}`);
      else if (command.kind === 'deal' && command.deal) router.push({ pathname: '/deals/[uuid]', params: { uuid: command.deal } });
      else router.replace('/');
    },
    [],
  );

  const listen = useCallback(async () => {
    const available = ExpoSpeechRecognitionModule.isRecognitionAvailable() && ExpoSpeechRecognitionModule.supportsOnDeviceRecognition();
    if (!available || !(await ExpoSpeechRecognitionModule.requestPermissionsAsync()).granted) return;
    setHeard(null);
    setListening(true);
    ExpoSpeechRecognitionModule.start({ lang: language, interimResults: false, requiresOnDeviceRecognition: true, addsPunctuation: false, continuous: false });
  }, [language]);

  function say(index: number, id: number) {
    const script = brief?.script ?? [];
    if (index >= script.length) {
      setPlaying(false);
      setLine(null);
      live.current.at = 0;
      void listen();
      return;
    }
    live.current.at = index;
    setLine(index);
    Speech.speak(script[index] ?? '', {
      language,
      rate: 0.95,
      onDone: () => {
        if (live.current.run === id) say(index + 1, id);
      },
    });
  }

  const stop = () => {
    live.current.run += 1;
    void Speech.stop();
    return live.current.run;
  };
  const play = () => {
    const id = stop();
    setPlaying(true);
    say(live.current.at, id);
  };
  const pause = () => {
    stop();
    setPlaying(false);
  };
  const skip = () => {
    const id = stop();
    if (playing) say(live.current.at + 1, id);
    else {
      live.current.at = Math.min(live.current.at + 1, (brief?.script.length ?? 1) - 1);
      setLine(live.current.at);
    }
  };
  const readInstead = () => {
    stop();
    setPlaying(false);
    setLine(null);
    live.current.at = 0;
  };

  useSpeechRecognitionEvent('result', (event) => {
    if (!event.isFinal || !brief) return;
    const text = event.results[0]?.transcript ?? '';
    setHeard(text);
    setListening(false);
    const command = matchCommand(text, brief.commands);
    if (command) run(command);
  });
  useSpeechRecognitionEvent('end', () => setListening(false));
  useSpeechRecognitionEvent('error', () => setListening(false));

  if (brief === null) return <Screen>{null}</Screen>;

  const save = async (next: NonNullable<typeof settings>) => {
    try {
      setSettings(await saveBriefSettings({ ...next, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || next.timezone }));
      setProblem(null);
      toast({ message: t('agents.brief.settings.saved') });
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };
  const example = brief.commands[0]?.label ?? t('agents.brief.commands.today');

  return (
    <Screen>
      <TitleBar title={t('agents.brief.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('agents.brief.description')}</Text>
      <View style={styles.controls}>
        {playing ? <Button icon={Pause} label={t('agents.brief.player.pause')} onPress={pause} /> : <Button icon={Play} label={line === null ? t('agents.brief.player.play') : t('agents.brief.player.resume')} onPress={play} />}
        <Button variant="secondary" icon={SkipForward} label={t('agents.brief.player.skip')} onPress={skip} />
        <Button variant="secondary" icon={BookOpen} label={t('agents.brief.player.readInstead')} onPress={readInstead} />
      </View>
      <SectionTitle>{t('agents.brief.player.transcript')}</SectionTitle>
      <Card style={styles.transcript}>
        {brief.script.map((text, index) => (
          <Text key={`${index}-${text}`} variant={line === index ? 'bodyLarge' : 'body'} tone={line === null || line === index ? 'body' : 'muted'}>
            {text}
          </Text>
        ))}
      </Card>
      <SectionTitle>{t('agents.brief.commands.title')}</SectionTitle>
      {brief.commands.map((command) => (
        <Button key={command.label} variant={command.kind === 'call' ? 'primary' : 'secondary'} icon={ICONS[command.kind]} label={command.label} onPress={() => run(command)} fullWidth />
      ))}
      <Button variant="secondary" icon={Mic} label={listening ? t('agents.brief.player.listening') : t('agents.brief.player.listen')} onPress={() => void listen()} fullWidth />
      {heard !== null ? (
        <Callout
          tone={matchCommand(heard, brief.commands) ? 'info' : 'warning'}
          title={t('agents.brief.player.heard', { text: heard })}
          body={matchCommand(heard, brief.commands) ? undefined : t('agents.brief.player.notUnderstood', { example })}
          live
        />
      ) : null}
      {settings ? (
        <>
          <SectionTitle>{t('agents.brief.settings.title')}</SectionTitle>
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          <SwitchRow label={t('agents.brief.settings.enabled')} description={t('agents.brief.settings.enabledHint')} value={settings.enabled} onValueChange={(enabled) => void save({ ...settings, enabled })} />
          <PillGroup
            label={t('agents.brief.settings.time')}
            value={settings.at}
            options={[...new Set([...BRIEF_TIMES, settings.at])].sort().map((at) => ({ value: at, label: new Intl.DateTimeFormat(i18n.language, { timeStyle: 'short', timeZone: 'UTC' }).format(new Date(`2026-01-01T${at}:00Z`)) }))}
            onChange={(at) => void save({ ...settings, at })}
          />
          <Text variant="title">{t('agents.brief.settings.sections')}</Text>
          {BRIEF_SECTIONS.map((section: BriefSection) => (
            <SwitchRow
              key={section}
              label={t(`agents.brief.settings.names.${section}`)}
              value={settings.sections.includes(section)}
              onValueChange={(on) => void save({ ...settings, sections: BRIEF_SECTIONS.filter((s) => (s === section ? on : settings.sections.includes(s))) })}
            />
          ))}
          <Text tone="muted">{t('agents.brief.settings.privacy')}</Text>
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  controls: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  transcript: { gap: theme.space.sm },
}));
