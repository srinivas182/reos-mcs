import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { openLegal } from '@/lib/consents';
import { Text } from '@/ui';

/** "By continuing, you agree to the Terms of use and the Privacy notice" under the sign-in options (A6.3). */
export function AgreementNote() {
  const { t } = useTranslation();

  return (
    <Text variant="caption" tone="muted" style={styles.note}>
      {t('consents.agree.lead')}{' '}
      <Text variant="caption" tone="action" accessibilityRole="link" onPress={() => openLegal('terms')} style={styles.link}>
        {t('consents.agree.terms')}
      </Text>{' '}
      {t('consents.agree.and')}{' '}
      <Text variant="caption" tone="action" accessibilityRole="link" onPress={() => openLegal('privacy')} style={styles.link}>
        {t('consents.agree.privacy')}
      </Text>
      .
    </Text>
  );
}

const styles = StyleSheet.create({
  note: { textAlign: 'center' },
  link: { textDecorationLine: 'underline' },
});
