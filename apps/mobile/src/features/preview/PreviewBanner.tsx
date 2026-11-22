import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { View } from 'react-native';
import { StyleSheet } from 'react-native-unistyles';

import { appConfig } from '@/lib/monitoring';
import { Text } from '@/ui';

/**
 * Shown on every screen of the private preview (P7.0): it's for testing,
 * nothing in it is real, and the one code every verification uses.
 */
export function PreviewBanner() {
  const { t } = useTranslation();
  const [code, setCode] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    void appConfig().then((config) => {
      if (live) setCode(config?.preview?.code ?? null);
    });

    return () => {
      live = false;
    };
  }, []);

  if (code === null) return null;

  return (
    <View accessible accessibilityLabel={`${t('preview.title')} ${t('preview.text', { code })}`} style={styles.banner}>
      <Text style={styles.text}>
        <Text weight="semibold" style={styles.text}>
          {t('preview.title')}
        </Text>{' '}
        {t('preview.text', { code })}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  banner: {
    paddingTop: rt.insets.top + theme.space.sm,
    paddingBottom: theme.space.sm,
    paddingHorizontal: theme.space.lg,
    backgroundColor: theme.colors.toneInfoBg,
  },
  text: { color: theme.colors.toneInfoFg, textAlign: 'center' },
}));
