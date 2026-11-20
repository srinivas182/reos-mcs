import * as LocalAuthentication from 'expo-local-authentication';

import { i18n } from '@/lib/i18n';
import { setBiometricEnabled } from '@/lib/session';

/** Turns Face ID or fingerprint on after the system confirms it works. */
export async function enableBiometric(): Promise<boolean> {
  const result = await LocalAuthentication.authenticateAsync({ promptMessage: i18n.t('mobile.biometric.prompt'), cancelLabel: i18n.t('common.cancel') });
  await setBiometricEnabled(result.success);

  return result.success;
}

