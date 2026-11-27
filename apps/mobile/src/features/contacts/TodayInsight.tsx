import { fixBeforeSending } from '@/lib/compliance';
import { router } from 'expo-router';
import { MessageSquareText, Phone, Sparkles } from 'lucide-react-native';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type Contact, type Insight, logTouch, reachUri } from '@/lib/crm';
import { Button, Card, Eyebrow, Icon, Text, useUndoToast } from '@/ui';

type Props = { contact: Contact; insight: Insight; onDone: () => void };

/**
 * Do this now (Agent App Journey screen 6, B8.3): one person and one step,
 * with why from their CRM activity, and the text drafted in the agent's
 * voice. Sent from the agent's own phone, then logged back to the CRMs.
 */
export function TodayInsight({ contact, insight, onDone }: Props) {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const phone = contact.phones[0] ?? null;

  const reach = async (channel: 'call' | 'text') => {
    if (!phone) return;
    // Checked and fixed before it opens in Messages (B13.1).
    const { text, changed } = channel === 'text' ? await fixBeforeSending('text', insight.draft ?? '') : { text: '', changed: [] };
    if (changed.length > 0) toast({ message: t('compliance.fixedNote', { phrases: changed.join(', ') }) });
    await Linking.openURL(reachUri(channel, phone, text));
    if (await logTouch(contact.id, channel, channel === 'text' ? text : null)) {
      toast({ message: t('crm.hub.today.logged') });
      onDone();
    }
  };

  return (
    <Card>
      <View style={styles.head}>
        <Icon icon={Sparkles} color="action" size={22} />
        <Eyebrow>{t('crm.hub.today.title')}</Eyebrow>
      </View>
      <Text variant="lead" weight="bold">
        {insight.next_step}
      </Text>
      <Text>{insight.why}</Text>
      {insight.draft && insight.channel === 'text' ? <Text tone="muted">{`“${insight.draft}”`}</Text> : null}
      <View style={styles.actions}>
        {phone && insight.channel === 'text' ? <Button label={t('crm.hub.today.textNow')} icon={MessageSquareText} onPress={() => void reach('text')} /> : null}
        {phone && insight.channel !== 'text' ? <Button label={t('crm.hub.today.callNow')} icon={Phone} onPress={() => void reach('call')} /> : null}
        <Button variant="ghost" label={t('crm.hub.today.open', { name: contact.name })} onPress={() => router.push({ pathname: '/contacts/[id]', params: { id: contact.id } })} />
      </View>
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  head: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
}));
