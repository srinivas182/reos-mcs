import type { ReactNode } from 'react';
import { Modal, Pressable, ScrollView, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Button } from './Button';
import { Text } from './Text';

export type SheetProps = {
  open: boolean;
  onClose: () => void;
  title: string;
  children: ReactNode;
};

/**
 * A bottom sheet for short tasks and choices. Tap outside, the Close button,
 * or the Android back button closes it; screen readers stay inside it.
 */
export function Sheet({ open, onClose, title, children }: SheetProps) {
  const { t } = useTranslation();

  return (
    <Modal visible={open} transparent animationType="slide" onRequestClose={onClose} statusBarTranslucent navigationBarTranslucent>
      <Pressable accessibilityElementsHidden importantForAccessibility="no-hide-descendants" style={styles.scrim} onPress={onClose} />
      <View accessibilityViewIsModal style={styles.sheet}>
        <View style={styles.handle} />
        <View style={styles.header}>
          <Text variant="title" style={styles.title}>
            {title}
          </Text>
          <Button label={t('common.close')} variant="ghost" onPress={onClose} />
        </View>
        <ScrollView contentContainerStyle={styles.body}>{children}</ScrollView>
      </View>
    </Modal>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  scrim: { flex: 1, backgroundColor: theme.colors.scrim },
  sheet: {
    maxHeight: '85%',
    backgroundColor: theme.colors.surface,
    borderTopLeftRadius: theme.radius.lg,
    borderTopRightRadius: theme.radius.lg,
    paddingBottom: rt.insets.bottom + theme.space.lg,
  },
  handle: { alignSelf: 'center', width: 40, height: 5, borderRadius: 3, backgroundColor: theme.colors.border, marginTop: theme.space.sm },
  header: { flexDirection: 'row', alignItems: 'center', paddingHorizontal: theme.space.lg, paddingTop: theme.space.sm },
  title: { flex: 1 },
  body: { padding: theme.space.lg, gap: theme.space.lg },
}));
