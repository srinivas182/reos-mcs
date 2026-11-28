import * as WebBrowser from 'expo-web-browser';
import { router, useFocusEffect } from 'expo-router';
import { ScrollText, ShieldAlert } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { webBaseUrl } from '@/lib/api';
import { addRule, checkRule, loadPolicies, type Policies, type TrecConflict } from '@/lib/brokerage';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * Policies on the phone (A15.2, broker journey screens 5 and 6): the
 * manual and advertising policy at a glance, and the brokerage's rules,
 * each checked against TREC as it's written.
 */
export default function PoliciesScreen() {
  const { t } = useTranslation();
  const [policies, setPolicies] = useState<Policies | null>(null);
  const [topic, setTopic] = useState('');
  const [text, setText] = useState('');
  const [live, setLive] = useState<TrecConflict | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadPolicies().then(setPolicies);
    }, []),
  );

  useEffect(() => {
    let current = true;
    const timer = setTimeout(() => {
      void checkRule(text).then((c) => current && setLive(c));
    }, 600);

    return () => {
      current = false;
      clearTimeout(timer);
    };
  }, [text]);

  const conflictLabel = (c: TrecConflict) => `${t('brokerage.policies.conflict', { citation: c.citation })}: ${c.rule_text}`;

  return (
    <Screen>
      <TitleBar title={t('brokerage.policies.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('brokerage.policies.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}
      {policies ? (
        <>
          <ListRow
            icon={ScrollText}
            title={t('brokerage.policies.manual')}
            subtitle={
              policies.manual
                ? `${t('brokerage.policies.manualVersion', { version: policies.manual.version, file: policies.manual.file_name })} · ${t('brokerage.policies.manualConflicts', { count: policies.manual.conflicts })}`
                : t('brokerage.policies.noManual')
            }
            onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/organisation/virtual-broker`)}
          />
          <ListRow
            icon={ScrollText}
            title={t('brokerage.policies.advertising')}
            subtitle={policies.advertising.approval_all ? t('brokerage.policies.approvalAll') : policies.advertising.approval_new_agents ? t('brokerage.policies.approvalNew') : t('brokerage.policies.approvalNone')}
            onPress={() => void WebBrowser.openBrowserAsync(`${webBaseUrl()}/organisation/advertising`)}
          />

          <SectionTitle>{t('brokerage.policies.rules')}</SectionTitle>
          <Text tone="muted">{policies.can_manage ? t('brokerage.policies.rulesHint') : t('brokerage.policies.readOnly')}</Text>
          {policies.can_manage ? (
            <Card>
              <TextField label={t('brokerage.policies.topic')} value={topic} maxLength={120} onChangeText={setTopic} />
              <TextField label={t('brokerage.policies.text')} value={text} maxLength={4000} multiline onChangeText={setText} />
              {live ? <Callout tone="danger" title={conflictLabel(live)} body={t('brokerage.policies.conflictHint')} /> : null}
              <Button
                label={t('brokerage.policies.add')}
                loading={busy}
                disabled={topic.trim().length < 2 || text.trim().length < 5}
                onPress={() => {
                  setBusy(true);
                  setError(null);
                  addRule(topic, text)
                    .then((rule) => {
                      setPolicies((now) => (now ? { ...now, rules: [rule, ...now.rules] } : now));
                      setTopic('');
                      setText('');
                      setLive(null);
                    })
                    .catch((e: Error) => setError(e.message))
                    .finally(() => setBusy(false));
                }}
              />
            </Card>
          ) : null}
          {policies.rules.length === 0 ? <EmptyState icon={<Icon icon={ScrollText} color="action" size={32} />} title={t('brokerage.policies.none')} description={t('brokerage.policies.noneHint')} /> : null}
          {policies.rules.map((rule) => (
            <ListRow key={rule.id} icon={rule.conflict ? ShieldAlert : ScrollText} tone={rule.conflict ? 'danger' : 'info'} title={rule.topic} subtitle={rule.conflict ? `${rule.text}\n${conflictLabel(rule.conflict)}` : rule.text} />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
