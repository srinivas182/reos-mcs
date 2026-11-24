import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadCelebration } from '@/lib/content-calendar';
import { Callout, Screen, TitleBar } from '@/ui';

/**
 * A celebration moment (agent screen 163; B12.3), opened from its
 * notification or My posts: the milestone's designs and captions, free,
 * shown as Pick a version with the milestone's badge.
 */
export default function CelebrationScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    void loadCelebration(id).then((c) => {
      if (c?.generation) {
        router.replace({ pathname: '/social/versions/[id]', params: { id: c.generation.id, milestone: c.milestone } });
      } else {
        setMissing(true);
      }
    });
  }, [id]);

  return (
    <Screen>
      <TitleBar title={t('studio.celebrate.title')} onBack={() => router.back()} />
      {missing ? <Callout tone="info" title={t('studio.celebrate.none')} /> : null}
    </Screen>
  );
}
