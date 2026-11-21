import { Stack } from 'expo-router';
import { useUnistyles } from 'react-native-unistyles';

/** Getting started (mobile journey screens 1–11): before and just after sign-up. */
export default function OnboardingLayout() {
  const { theme } = useUnistyles();

  return <Stack screenOptions={{ headerShown: false, contentStyle: { backgroundColor: theme.colors.page } }} />;
}
