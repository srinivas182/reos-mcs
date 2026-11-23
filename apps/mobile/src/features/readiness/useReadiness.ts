import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { loadReadiness, type ReadinessMap } from '@/lib/practice';

/** Readiness, loaded whenever the screen comes into view: undefined while loading, null if it couldn't load. */
export function useReadiness() {
  const [map, setMap] = useState<ReadinessMap | null | undefined>(undefined);

  useFocusEffect(
    useCallback(() => {
      let active = true;
      void loadReadiness()
        .catch(() => null)
        .then((found) => active && setMap(found));

      return () => {
        active = false;
      };
    }, []),
  );

  return map;
}
