import { BookmarkCheck, BookmarkPlus } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { saveFlashcard } from '@/lib/flashcards';
import { Button, Callout } from '@/ui';

/** Save as flashcard (A9.2d): from a check-in, an Ask answer or answer review. The card joins the next burst. */
export function SaveFlashcard({ source, front, back, questionId }: { source: 'checkin' | 'ask' | 'review'; front: string; back: string; questionId?: number }) {
  const { t } = useTranslation();
  const [state, setState] = useState<'idle' | 'saving' | 'saved' | 'failed'>('idle');

  if (state === 'saved') return <Button variant="ghost" icon={BookmarkCheck} label={t('practice.flashcards.saved')} disabled onPress={() => undefined} />;

  return (
    <>
      <Button
        variant="secondary"
        icon={BookmarkPlus}
        loading={state === 'saving'}
        label={t('practice.flashcards.save')}
        onPress={() => {
          setState('saving');
          void saveFlashcard(source, front, back, questionId).then((ok) => setState(ok ? 'saved' : 'failed'));
        }}
      />
      {state === 'failed' ? <Callout tone="danger" title={t('practice.errors.save')} live /> : null}
    </>
  );
}
