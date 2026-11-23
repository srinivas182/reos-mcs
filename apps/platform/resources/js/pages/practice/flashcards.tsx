import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { FlashcardSession } from '@/components/practice/flashcard-session';
import type { Deck, Topic } from '@/components/practice/types';

/** Flashcards on the web (the phone's screen 31, A9.2d). */
export default function FlashcardsPage({
    deck,
    topic,
}: {
    deck: Deck;
    topic: Topic | null;
}) {
    const { t } = useTranslation();
    const title = topic
        ? t('practice.flashcards.topicTitle', {
              topic: t(`practice.topics.${topic}`),
          })
        : t('practice.flashcards.title');

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={t('practice.flashcards.description')}
                />
                <FlashcardSession deck={deck} />
            </div>
        </>
    );
}
