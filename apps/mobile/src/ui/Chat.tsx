import type { ReactNode } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

type ChatBubbleProps = {
  from: 'me' | 'assistant' | 'person';
  /** Plain text, or rich content for answers (steps, bold totals). */
  children: ReactNode;
  /** Where an AI answer came from: "Texas Real Estate Principles, lesson 12". */
  source?: string;
  /** Name of the sender for screen readers when it's another person. */
  senderName?: string;
};

/** A message in Ask or in chats. My messages are blue on the right; replies are white cards on the left. */
export function ChatBubble({ from, children, source, senderName }: ChatBubbleProps) {
  const { t } = useTranslation();
  styles.useVariants({ from });
  const who = from === 'me' ? t('chat.you') : from === 'assistant' ? t('chat.assistant') : (senderName ?? '');

  return (
    <View accessible accessibilityLabel={typeof children === 'string' ? `${who}: ${children}` : undefined} style={styles.bubble}>
      {typeof children === 'string' ? <Text variant="bodyLarge" style={from === 'me' ? styles.meText : undefined}>{children}</Text> : children}
      {source ? (
        <Text variant="caption" tone="muted">
          {t('chat.source', { source })}
        </Text>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  bubble: {
    maxWidth: '88%',
    gap: theme.space.sm,
    paddingHorizontal: theme.space.lg,
    paddingVertical: theme.space.md,
    borderRadius: theme.m.radius.card,
    variants: {
      from: {
        me: { alignSelf: 'flex-end', backgroundColor: theme.colors.action, borderBottomRightRadius: 6 },
        assistant: { alignSelf: 'stretch', maxWidth: '100%', backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border },
        person: { alignSelf: 'flex-start', backgroundColor: theme.colors.surface, borderWidth: 1, borderColor: theme.colors.border, borderBottomLeftRadius: 6 },
      },
    },
  },
  meText: { color: theme.colors.onAction },
}));
