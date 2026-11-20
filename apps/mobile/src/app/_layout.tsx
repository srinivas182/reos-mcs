import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { I18nextProvider } from 'react-i18next';

import { colors } from '@reos/tokens';

import { i18n } from '@/lib/i18n';

export default function RootLayout() {
  return (
    <I18nextProvider i18n={i18n}>
      <StatusBar style="light" />
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.navy },
        }}
      />
    </I18nextProvider>
  );
}
