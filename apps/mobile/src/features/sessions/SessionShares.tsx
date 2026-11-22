/**
 * What the host shared in a session (B6.3b quick actions), on the phone:
 * lessons open in Learn, practice questions are answered right here.
 */
import { router } from 'expo-router';
import { BookOpen } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { Session } from '@/lib/sessions';
import { Card, Eyebrow, Icon, OptionGroup, Text } from '@/ui';

type Share = Session['shares'][number];
type Question = Share['questions'][number];

function PracticeQuestion({ question, n }: { question: Question; n: number }) {
  const { t } = useTranslation();
  const [chosen, setChosen] = useState<string | null>(null);
  const right = chosen === String(question.correct);

  return (
    <View style={styles.block}>
      <Text weight="bold">
        {n}. {question.question}
      </Text>
      <OptionGroup
        label={question.question}
        lettered
        options={question.options.map((label, i) => ({ value: String(i), label }))}
        value={chosen}
        onChange={setChosen}
        correct={chosen === null ? undefined : String(question.correct)}
      />
      {chosen !== null ? (
        <Text accessibilityLiveRegion="polite">
          <Text weight="bold">{right ? t('sessions.share.right') : t('sessions.share.wrong')}</Text> {question.explanation}{' '}
          <Text tone="muted">{t('sessions.share.source', { source: question.source })}</Text>
        </Text>
      ) : null}
    </View>
  );
}

export function SessionShares({ shares }: { shares: Share[] }) {
  const { t } = useTranslation();

  if (shares.length === 0) return null;

  return (
    <View style={styles.block}>
      <Eyebrow>{t('sessions.share.shared')}</Eyebrow>
      {shares.map((share) =>
        share.kind === 'lesson' && share.lesson ? (
          <Card
            key={share.id}
            onPress={() => router.push({ pathname: '/learn/[id]', params: { id: String(share.lesson?.id) } })}
            accessibilityLabel={`${t('sessions.share.openLesson')}: ${share.lesson.title}`}
          >
            <View style={styles.row}>
              <Icon icon={BookOpen} color="action" />
              <View style={styles.text}>
                <Text weight="bold">{share.lesson.title}</Text>
                <Text tone="muted">{share.lesson.course}</Text>
              </View>
            </View>
          </Card>
        ) : (
          <Card key={share.id}>
            <View style={styles.block}>
              <Text weight="bold">{t('sessions.share.practice', { topic: t(`onboarding.topics.${share.topic as 'agency'}`) })}</Text>
              {share.questions.map((question, i) => (
                <PracticeQuestion key={question.id} question={question} n={i + 1} />
              ))}
            </View>
          </Card>
        ),
      )}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  block: { gap: theme.space.md },
  row: { flexDirection: 'row', gap: theme.space.md, alignItems: 'flex-start' },
  text: { flex: 1, gap: 2 },
}));
