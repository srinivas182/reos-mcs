import { Stack } from 'expo-router';
import { useUnistyles } from 'react-native-unistyles';

/** Signed-out screens: sign in, phone number, code. */
export default function AuthLayout() {
  const { theme } = useUnistyles();

  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: theme.colors.page } }} />;
}
