import { router, useLocalSearchParams } from 'expo-router';
import { Gauge, Lightbulb, ShieldCheck, TriangleAlert } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { type ComplianceCode, fillerLine, loadReview, type VideoReview } from '@/lib/video-practice';
import { Button, Card, Icon, Screen, SectionTitle, Text, TitleBar } from '@/ui';

const DIMENSIONS = ['pace', 'fillers', 'opening', 'proof', 'close'] as const;

/** A video practice review on the phone (A14.1b). */
export default function VideoReviewScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [review, setReview] = useState<VideoReview | null>(null);

  useEffect(() => {
    if (id) void loadReview(id).then(setReview);
  }, [id]);

  if (!review)
    return (
      <Screen>
        <TitleBar title={t('videopractice.title')} onBack={() => router.back()} />
      </Screen>
    );

  return (
    <Screen>
      <TitleBar title={t(`videopractice.kinds.${review.kind}`)} onBack={() => router.back()} />
      <Card>
        <Text weight="semibold">{t('videopractice.score', { score: review.score })}</Text>
        <Icon icon={Gauge} color="action" size={24} />
        <Text>{t('videopractice.pace.value', { wpm: review.wpm })}</Text>
        <Text tone="muted">{t(`videopractice.pace.${review.pace}`)}</Text>
        <Text tone="muted">{t('videopractice.duration', { seconds: review.duration_seconds })}</Text>
      </Card>
      <Card>
        <Text weight="semibold">{t('videopractice.fillersTitle')}</Text>
        <Text>{fillerLine(review.fillers, t)}</Text>
        {DIMENSIONS.map((d) => (
          <Text key={d} tone="muted">
            {t('videopractice.part', { label: t(`videopractice.dimensions.${d}`), score: review.scores[d] })}
          </Text>
        ))}
      </Card>

      <SectionTitle>{t('videopractice.fixesTitle')}</SectionTitle>
      {review.fixes.map((fix) => (
        <Card key={fix.dimension}>
          <Icon icon={Lightbulb} color="action" size={24} />
          <Text weight="semibold">{t(`videopractice.dimensions.${fix.dimension}`)}</Text>
          <Text>{fix.text}</Text>
          <Text tone="muted">{t('videopractice.tryLine', { line: fix.example })}</Text>
          <Button label={t('videopractice.lesson', { name: t(`roleplay.lessons.${fix.lesson}`) })} variant="ghost" onPress={() => router.push('/learn')} />
        </Card>
      ))}

      <Card>
        <Icon icon={review.compliance.length === 0 ? ShieldCheck : TriangleAlert} color="action" size={24} />
        <Text weight="semibold">{t('videopractice.compliance.title')}</Text>
        {review.compliance.length === 0 ? <Text>{t('videopractice.compliance.clean')}</Text> : <Text>{t('videopractice.compliance.hint')}</Text>}
        {review.compliance.map((issue, i) => (
          <Text key={`${issue.code}-${i}`}>{`• ${t(`compliance.engine.${issue.code as ComplianceCode}`)}${issue.phrase ? `: “${issue.phrase}”` : ''}`}</Text>
        ))}
      </Card>
      <Text tone="muted">{t(`videopractice.engine.${review.engine}`)}</Text>
      <Button label={t('videopractice.again')} onPress={() => router.replace('/video-practice')} />
    </Screen>
  );
}
