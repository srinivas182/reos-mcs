import { Redirect } from 'expo-router';

/**
 * `reos://connections?outcome=…`: where a tool's sign-in returns. The browser
 * sheet usually catches it first; if the system opens the app with it
 * instead, carry on from Today.
 */
export default function ConnectionsReturn() {
  return <Redirect href="/" />;
}
