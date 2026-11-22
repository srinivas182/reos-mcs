// Themes must be registered before any StyleSheet is created.
import '@/ui/unistyles';

import { SplashScreen, Stack, usePathname } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { I18nextProvider } from 'react-i18next';
import { useUnistyles } from 'react-native-unistyles';

import { AuthProvider, useAuth } from '@/lib/auth';
import { deviceLocale, i18n } from '@/lib/i18n';
import { appConfig, startMonitoring, trackScreen } from '@/lib/monitoring';
import { usePush } from '@/lib/push';
import { useShortcuts } from '@/lib/shortcuts';
import { useTodoReminderActions } from '@/lib/todoReminders';
import { useTodoQueueFlush } from '@/lib/todos';
import { useAuthGate } from '@/lib/useAuthGate';
import { initPreferences, OfflineBanner, ToastProvider } from '@/ui';

initPreferences(deviceLocale);

// Error tracking and analytics, if switched on in Settings › Integrations.
const monitoring = appConfig().then(startMonitoring);

// Keep the splash screen up until we know whether someone is signed in.
void SplashScreen.preventAutoHideAsync();

export default function RootLayout() {
  const { theme } = useUnistyles();
  const pathname = usePathname();

  useEffect(() => {
    void monitoring.then(() => trackScreen(pathname));
  }, [pathname]);

  return (
    <I18nextProvider i18n={i18n}>
      <AuthProvider>
        <ToastProvider>
          <StatusBar style={theme.name === 'dark' ? 'light' : 'dark'} />
          <OfflineBanner />
          <Navigator />
        </ToastProvider>
      </AuthProvider>
    </I18nextProvider>
  );
}

/** The screens, with signed-out people kept on sign-in and signed-in people off it. */
function Navigator() {
  const { theme } = useUnistyles();
  const { status } = useAuth();
  const pathname = usePathname();

  useAuthGate();
  // Push (B6.2b): renew this phone's token and open what a tap points to.
  usePush(status === 'signedIn', pathname);
  // Voice to-dos (B4.3b): Done and Snooze on reminders, the offline queue, and the "Add a to-do" shortcut.
  useTodoReminderActions();
  useTodoQueueFlush();
  useShortcuts(status === 'signedIn');

  useEffect(() => {
    if (status !== 'loading') {
      void SplashScreen.hideAsync();
    }
  }, [status]);

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: theme.colors.page },
      }}
    />
  );
}
