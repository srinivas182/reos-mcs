// Themes must be registered before any StyleSheet is created.
import '@/ui/unistyles';

import { Stack, usePathname } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { I18nextProvider } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { deviceLocale, i18n } from '@/lib/i18n';
import { fetchAppConfig, startMonitoring, trackScreen } from '@/lib/monitoring';
import { initPreferences, OfflineBanner, ToastProvider } from '@/ui';

initPreferences(deviceLocale);

// Error tracking and analytics, if switched on in Settings › Integrations.
const monitoring = fetchAppConfig().then(startMonitoring);

export default function RootLayout() {
  const { theme } = useUnistyles();
  const pathname = usePathname();

  useEffect(() => {
    void monitoring.then(() => trackScreen(pathname));
  }, [pathname]);

  return (
    <I18nextProvider i18n={i18n}>
      <ToastProvider>
        <StatusBar style={theme.name === 'dark' ? 'light' : 'dark'} />
        <OfflineBanner />
        <Stack
          screenOptions={{
            headerShown: false,
            contentStyle: { backgroundColor: theme.colors.page },
          }}
        />
      </ToastProvider>
    </I18nextProvider>
  );
}
