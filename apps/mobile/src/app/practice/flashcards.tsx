import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { Layers } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import { burstQueue, type Card, type Deck, loadDeck, pendingReviews, rate } from '@/lib/flashcards';
import { AGAIN, GOOD, HARD, type Rating } from '@/lib/fsrs';
import { asTopic, countdown } from '@/lib/practice';
import { Button, Callout, Card as Surface, EmptyState, Icon, Screen, Text, TitleBar } from '@/ui';

const BURST = 120;

/**
 * Flashcards (candidate screen 31, A9.2d): a two-minute burst of the cards
 * due, then today's new ones, with a counter. Tap the card to see the
 * answer, then Again (back in ten minutes, so again in this burst), Hard or
 * Got it. Works fully offline: reviews are scheduled on the phone and
 * synced when it's back online. `topic` keeps to one topic (a fix plan).
 */
export default function FlashcardsScreen() {
  const { t } = useTranslation();
  const { theme } = useUnistyles();
  const { topic: asked } = useLocalSearchParams<{ topic?: string }>();
  const topic = asTopic(asked);
  const [deck, setDeck] = useState<Deck | null | undefined>(undefined);
  const [offline, setOffline] = useState(false);
  const [queue, setQueue] = useState<Card[]>([]);
  const [started, setStarted] = useState(false);
  const [flipped, setFlipped] = useState(false);
  const [left, setLeft] = useState(BURST);
  const [done, setDone] = useState(0);
  const [pending, setPending] = useState(0);

  const load = useCallback(() => {
    let active = true;
    void loadDeck(topic).then((found) => {
      if (!active) return;
      setDeck(found.deck);
      setOffline(found.offline);
      setQueue(found.deck ? burstQueue(found.deck) : []);
      setPending(pendingReviews().length);
      setStarted(false);
      setDone(0);
      setLeft(BURST);
    });

    return () => {
      active = false;
    };
  }, [topic]);

  useFocusEffect(load);

  const card = queue[0];
  const over = started && (left === 0 || card === undefined);

  useEffect(() => {
    if (!started || over) return;
    const timer = setInterval(() => setLeft((s) => Math.max(0, s - 1)), 1000);

    return () => clearInterval(timer);
  }, [started, over]);

  const answer = (rating: Rating) => {
    if (!card) return;
    const updated = rate(card, rating);
    setFlipped(false);
    setDone((n) => n + 1);
    setQueue((q) => (rating === AGAIN ? [...q.slice(1), updated] : q.slice(1)));
    setPending(pendingReviews().length);
  };

  const title = topic ? t('practice.flashcards.topicTitle', { topic: t(`practice.topics.${topic}`) }) : t('practice.flashcards.title');

  return (
    <Screen>
      <TitleBar title={title} onBack={() => router.back()} />
      <Text tone="muted">{t('practice.flashcards.description')}</Text>
      {offline || pending > 0 ? <Callout tone="info" title={t('practice.flashcards.offline')} body={pending > 0 ? t('practice.flashcards.pending', { count: pending }) : undefined} /> : null}
      {deck === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : !deck || (queue.length === 0 && !started) ? (
        <EmptyState icon={<Icon icon={Layers} color="action" size={32} />} title={t('practice.flashcards.emptyTitle')} description={t('practice.flashcards.empty')} />
      ) : !started ? (
        <Surface>
          <Text>{`${t('practice.flashcards.due', { count: deck.due })} · ${t('practice.flashcards.newLeft', { count: Math.min(deck.new_left, deck.cards.filter((c) => c.new).length) })}`}</Text>
          <Button label={t('practice.flashcards.start')} onPress={() => setStarted(true)} fullWidth />
        </Surface>
      ) : over || !card ? (
        <Callout tone="success" title={t('practice.flashcards.doneTitle')} body={t('practice.flashcards.doneBody', { count: done })} live action={<Button label={t('practice.flashcards.another')} onPress={load} />} />
      ) : (
        <>
          <View style={styles.row}>
            <Text tone="muted">{t('practice.flashcards.counter', { n: done + 1, left: queue.length - 1 })}</Text>
            <Text tone="muted" accessibilityLabel={t('practice.flashcards.timeLeft', { time: countdown(left) })}>
              {countdown(left)}
            </Text>
          </View>
          <Pressable accessibilityRole="button" accessibilityLabel={flipped ? card.back : `${card.front}. ${t('practice.flashcards.show')}`} onPress={() => setFlipped(true)} disabled={flipped}>
            <Surface style={styles.card}>
              <Text variant="caption" tone="muted">
                {t('practice.flashcards.front')}
              </Text>
              <Text variant="lead" weight="semibold">
                {card.front}
              </Text>
              {flipped ? (
                <>
                  <Text variant="caption" tone="muted">
                    {t('practice.flashcards.back')}
                  </Text>
                  <Text variant="bodyLarge">{card.back}</Text>
                </>
              ) : null}
            </Surface>
          </Pressable>
          {flipped ? (
            <>
              <View style={styles.row}>
                <Button variant="secondary" label={t('practice.flashcards.again')} onPress={() => answer(AGAIN)} />
                <Button variant="secondary" label={t('practice.flashcards.hard')} onPress={() => answer(HARD)} />
                <Button label={t('practice.flashcards.good')} onPress={() => answer(GOOD)} />
              </View>
              <Text variant="caption" tone="muted">
                {t('practice.flashcards.againHint')}
              </Text>
            </>
          ) : (
            <Button label={t('practice.flashcards.show')} onPress={() => setFlipped(true)} fullWidth />
          )}
        </>
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: theme.space.xs },
  card: { minHeight: 180, gap: theme.space.xs },
}));
