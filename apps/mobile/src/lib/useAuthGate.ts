import { router, useSegments } from 'expo-router';
import { useEffect } from 'react';

import { useAuth } from '@/lib/auth';

/**
 * Keeps people on the right screens: signed out → sign-in; locked → Face ID
 * unlock; signed in → wherever the sign-in said (new phone, Face ID offer or
 * Today), never back to the sign-in screens.
 */
export function useAuthGate(): void {
  const { status, landing, clearLanding } = useAuth();
  const segments = useSegments();
  const inAuth = segments[0] === '(auth)';
  const onLock = segments[0] === 'lock';

  useEffect(() => {
    if (status === 'loading') {
      return;
    }

    if (status === 'signedOut' && !inAuth) {
      router.replace('/sign-in');
    } else if (status === 'locked' && !onLock) {
      router.replace('/lock');
    } else if (status === 'signedIn' && (inAuth || onLock || landing !== null)) {
      router.replace(landing ?? '/');
      clearLanding();
    }
  }, [status, inAuth, onLock, landing, clearLanding]);
}
