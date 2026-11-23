import { Sparkles } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import type { Insight } from '@/lib/crm';
import { timeAgo } from '@/lib/time';
import { Button, Card, Eyebrow, Icon, Text } from '@/ui';

type Props = { insight: Insight | null; busy: boolean; onAsk: () => void };

/**
 * The AI summary and next step (B8.3): free for agents, written from every
 * source, kept until the contact changes. It suggests; the agent acts.
 */
export function InsightBlock({ insight, busy, onAsk }: Props) {
  const { t, i18n } = useTranslation();

  return (
    <Card accessibilityLiveRegion="polite">
      <View style={styles.head}>
        <Icon icon={Sparkles} color="action" size={22} />
        <Text variant="lead" weight="bold">
          {t('crm.hub.ai.summary')}
        </Text>
      </View>
      {insight === null ? (
        <>
          <Text tone="muted">{t('crm.hub.ai.intro')}</Text>
          <Button label={busy ? t('crm.hub.ai.writing') : t('crm.hub.ai.summary')} icon={Sparkles} loading={busy} onPress={onAsk} />
        </>
      ) : (
        <>
          <Text>{insight.summary}</Text>
          <Eyebrow>{t('crm.hub.ai.nextStep')}</Eyebrow>
          <Text weight="bold">{insight.next_step}</Text>
          <Text>{`${t('crm.hub.ai.why')}: ${insight.why}`}</Text>
          {insight.draft ? (
            <>
              <Eyebrow>{t('crm.hub.ai.draft')}</Eyebrow>
              <Text>{insight.draft}</Text>
            </>
          ) : null}
          <Text tone="muted" variant="caption">
            {insight.engine === 'simulator' ? t('crm.hub.ai.simulated') : t('crm.hub.ai.written', { ago: timeAgo(insight.generated_at, i18n.language).toLocaleLowerCase(i18n.language) })}
          </Text>
        </>
      )}
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  head: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
}));
