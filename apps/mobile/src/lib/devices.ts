import type { Schemas } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';

export type Device = Schemas['DeviceResource'];

/** Signed-in phones and tablets; signs out a lost or old one. */
export function useDevices() {
  const [devices, setDevices] = useState<Device[]>([]);
  const { status } = useAuth();

  // Only once the saved token has loaded (right after launch it may not have).
  const load = useCallback(async () => {
    if (status !== 'signedIn') {
      return;
    }

    const { data } = await api().GET('/me/devices');
    setDevices(data?.data ?? []);
  }, [status]);

  useFocusEffect(
    useCallback(() => {
      void load();
    }, [load]),
  );

  return { devices, reload: load };
}

