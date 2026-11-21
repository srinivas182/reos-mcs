import { MessageSquare, Mic } from 'lucide-react-native';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Icon } from './Icon';
import { Text } from './Text';

type AskBarProps = {
  /** What to ask about on this screen: "Ask anything about the exam". */
  placeholder: string;
  /** Opens Ask with the keyboard. */
  onPress: () => void;
  /** Starts a spoken question (hands-free, easier than typing). */
  onVoice: () => void;
  /** What the microphone does, for screen readers (default "Ask by voice"). */
  voiceLabel?: string;
};

/** The "Ask anything" bar above the tabs: one tap to type, or the blue microphone to speak. */
export function AskBar({ placeholder, onPress, onVoice, voiceLabel }: AskBarProps) {
  const { t } = useTranslation();

  return (
    <View style={styles.bar}>
      <Pressable accessibilityRole="button" accessibilityLabel={placeholder} onPress={onPress} style={styles.input}>
        <Icon icon={MessageSquare} color="action" size={20} />
        <Text variant="bodyLarge" tone="muted" numberOfLines={1} style={styles.placeholder}>
          {placeholder}
        </Text>
      </Pressable>
      <Pressable accessibilityRole="button" accessibilityLabel={voiceLabel ?? t('ask.voice')} onPress={onVoice} hitSlop={4} style={({ pressed }) => [styles.mic, pressed && styles.pressed]}>
        <Icon icon={Mic} color="onAction" size={22} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: theme.touch + 8,
    marginHorizontal: theme.m.gutter,
    marginVertical: theme.space.sm,
    paddingLeft: theme.space.lg,
    paddingRight: 4,
    borderRadius: theme.m.radius.pill,
    borderWidth: 1,
    borderColor: theme.colors.borderControl,
    backgroundColor: theme.colors.surface,
  },
  input: { flex: 1, flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, minHeight: theme.touch },
  placeholder: { flex: 1 },
  mic: { width: theme.touch, height: theme.touch, borderRadius: theme.m.radius.pill, backgroundColor: theme.colors.action, alignItems: 'center', justifyContent: 'center' },
  pressed: { opacity: 0.85 },
}));
