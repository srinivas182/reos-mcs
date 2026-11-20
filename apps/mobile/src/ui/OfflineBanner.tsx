import { useNetworkState } from 'expo-network';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Text } from './Text';

/**
 * A calm banner when the phone is offline, so people know why something didn't
 * load. Announced once by screen readers; disappears on its own when back online.
 */
export function OfflineBanner() {
  const { t } = useTranslation();
  const network = useNetworkState();
  const offline = network.isConnected === false || network.isInternetReachable === false;

  if (!offline) return null;

  return (
    <View accessible accessibilityRole="alert" accessibilityLiveRegion="polite" style={styles.banner}>
      <Text weight="medium" style={styles.text}>
        {t('common.offline')}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create((theme, rt) => ({
  banner: {
    paddingTop: rt.insets.top + theme.space.sm,
    paddingBottom: theme.space.sm,
    paddingHorizontal: theme.space.lg,
    backgroundColor: theme.colors.warningSurface,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.warning,
  },
  text: { color: theme.colors.text, textAlign: 'center' },
}));
