import { Redirect } from 'expo-router';

/**
 * `reos://voice`, the "Add a to-do" home-screen shortcut and (later) Siri:
 * Today with voice capture open (B4.3b).
 */
export default function VoiceRoute() {
  return <Redirect href={{ pathname: '/', params: { capture: 'voice' } }} />;
}
