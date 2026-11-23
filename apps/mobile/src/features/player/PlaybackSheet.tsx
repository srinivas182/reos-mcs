import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { SPEEDS } from '@/lib/player';
import { QUALITIES, type Quality, setPlayback, USE_PER_HOUR, usePlayback } from '@/lib/playback';
import { OptionGroup, PillGroup, Sheet, SwitchRow, Text } from '@/ui';

type Props = {
  open: boolean;
  onClose: () => void;
  cellular: boolean;
  speed: (typeof SPEEDS)[number];
  onSpeed: (speed: (typeof SPEEDS)[number]) => void;
  languages: ('en' | 'es')[];
  captions: 'en' | 'es';
  onCaptions: (language: 'en' | 'es') => void;
  audioOnly: boolean;
  onAudioOnly: (on: boolean) => void;
  /** No capped copies (a plain file): quality isn't offered. */
  qualities: boolean;
};

/**
 * Playback settings (candidate screen 26, Sprint 8 wrap-up): Quality, Data
 * saver, Speed, Captions and Audio only, with data use per hour. Auto on
 * Wi-Fi; 540p, or 360p with data saver, on mobile data.
 */
export function PlaybackSheet({ open, onClose, cellular, speed, onSpeed, languages, captions, onCaptions, audioOnly, onAudioOnly, qualities }: Props) {
  const { t } = useTranslation();
  const settings = usePlayback();

  return (
    <Sheet open={open} onClose={onClose} title={t('player.settings.title')}>
      <View style={styles.block}>
        <Text tone="muted">{cellular ? t('player.settings.onCellular') : t('player.settings.onWifi')}</Text>
        {qualities ? (
          <>
            <OptionGroup<Quality>
              label={t('player.settings.quality')}
              value={settings.quality}
              onChange={(quality) => setPlayback({ quality })}
              options={QUALITIES.map((q) => ({ value: q, label: t(`player.settings.qualities.${q}`), description: t('player.settings.use', { size: USE_PER_HOUR[q] }) }))}
            />
            <Text tone="muted" variant="caption">
              {t('player.settings.qualityHint')}
            </Text>
            <SwitchRow label={t('player.settings.dataSaver')} description={t('player.settings.dataSaverHint')} value={settings.dataSaver} onValueChange={(dataSaver) => setPlayback({ dataSaver })} />
          </>
        ) : null}
        <PillGroup label={t('player.speed')} value={String(speed)} options={SPEEDS.map((v) => ({ value: String(v), label: `${v}×` }))} onChange={(v) => onSpeed(Number(v) as (typeof SPEEDS)[number])} />
        {languages.length > 1 ? (
          <PillGroup label={t('player.captions')} value={captions} options={languages.map((l) => ({ value: l, label: t(`player.languages.${l}`) }))} onChange={onCaptions} />
        ) : null}
        <SwitchRow label={t('player.audioOnly')} description={`${t('player.audioOnlyHint')} ${t('player.settings.audioUse')}`} value={audioOnly} onValueChange={onAudioOnly} />
      </View>
    </Sheet>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.sm },
}));
