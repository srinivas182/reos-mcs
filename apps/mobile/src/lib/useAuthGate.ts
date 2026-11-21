import { router, useSegments } from 'expo-router';
import { useEffect } from 'react';

import { useAuth, type Me } from '@/lib/auth';
import { hasSignedInBefore } from '@/lib/onboarding';

/** Screens of the 60-second setup that only make sense once signed in. */
const SETUP = ['setup', 'plan'];

/** A candidate who hasn't done the 60-second setup yet (journey screen 8). */
export const needsSetup = (user: Me | null): boolean => user !== null && !user.onboarding.completed && user.onboarding.path === 'become_agent';

/**
 * Keeps people on the right screens:
 * - signed out → the path chooser on first launch (journey screen 1), Sign in after that;
 * - locked → Face ID unlock;
 * - signed in → the 60-second setup if it's due, otherwise wherever the
 *   sign-in said (new phone, Face ID offer or Today), never back to sign-in.
 */
export function useAuthGate(): void {
  const { status, user, landing, clearLanding } = useAuth();
  // Read as plain strings: the generated route types narrow this tuple.
  const segments: readonly string[] = useSegments();
  const group = segments[0];
  const screen = segments[1];
  const inAuth = group === '(auth)';
  const inOnboarding = group === '(onboarding)';
  const onLock = group === 'lock';
  const onSetup = inOnboarding && SETUP.includes(screen ?? '');

  useEffect(() => {
    if (status === 'loading') {
      return;
    }

    if (status === 'signedOut') {
      if (!inAuth && (!inOnboarding || onSetup)) {
        router.replace(hasSignedInBefore() ? '/sign-in' : '/start');
      }
    } else if (status === 'locked') {
      if (!onLock) {
        router.replace('/lock');
      }
    } else if (needsSetup(user)) {
      if (!onSetup) {
        router.replace('/setup');
        clearLanding();
      }
    } else if (inAuth || onLock || landing !== null) {
      router.replace(landing ?? '/');
      clearLanding();
    }
  }, [status, user, inAuth, inOnboarding, onLock, onSetup, landing, clearLanding]);
}
