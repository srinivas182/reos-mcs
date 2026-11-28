import * as WebBrowser from 'expo-web-browser';
import { router, useFocusEffect } from 'expo-router';
import { CheckCircle2, Circle, ClipboardCheck, GraduationCap, ScrollText, TriangleAlert, UserMinus, Users, Wallet } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { webBaseUrl } from '@/lib/api';
import { loadSetup, type Setup } from '@/lib/brokerage';
import { Button, Callout, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * Your brokerage on the phone (A15.1, broker journey screens 1–4): the
 * setup checklist, bring in your agents, and seats and who pays. The other
 * steps (policy manual, advertising policy, mentors) open on the web.
 */
export default function BrokerageScreen() {
  const { t } = useTranslation();
  const [setup, setSetup] = useState<Setup | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadSetup().then(setSetup);
    }, []),
  );

  const open = (step: Setup['steps'][number]) => {
    if (step.key === 'agents') return router.push('/brokerage/roster');
    if (step.key === 'seats') return router.push('/brokerage/seats');

    return void WebBrowser.openBrowserAsync(`${webBaseUrl()}${step.href}`);
  };

  return (
    <Screen>
      <TitleBar title={t('brokerage.setup.title')} onBack={() => router.back()} />
      {setup ? (
        <>
          <Text tone="muted">
            {setup.done === setup.total ? t('brokerage.setup.complete') : t('brokerage.setup.progress', { done: setup.done, total: setup.total })}
            {setup.trec_number ? ` · ${t('brokerage.setup.trec', { number: setup.trec_number })}` : ''}
          </Text>
          {setup.steps.map((step) => (
            <ListRow
              key={step.key}
              icon={step.done ? CheckCircle2 : Circle}
              tone={step.done ? 'success' : 'info'}
              title={t(`brokerage.setup.steps.${step.key}`)}
              subtitle={step.done ? t('brokerage.setup.done') : t('brokerage.setup.do')}
              onPress={() => void open(step)}
            />
          ))}
          <SectionTitle>{t('brokerage.roster.title')}</SectionTitle>
          <ListRow icon={Users} title={t('brokerage.roster.title')} subtitle={t('brokerage.roster.purpose')} onPress={() => router.push('/brokerage/roster')} />
          <ListRow icon={GraduationCap} title={t('brokerage.ce.title')} subtitle={t('brokerage.ce.purpose')} onPress={() => router.push('/brokerage/ce')} />
          <ListRow icon={UserMinus} title={t('brokerage.sponsorship.title')} subtitle={t('brokerage.sponsorship.purpose')} onPress={() => router.push('/brokerage/sponsorship')} />
          <ListRow icon={TriangleAlert} title={t('brokerage.oversight.title')} subtitle={t('brokerage.oversight.purpose')} onPress={() => router.push('/brokerage/oversight')} />
          <ListRow icon={ClipboardCheck} title={t('brokerage.approvals.title')} subtitle={t('brokerage.approvals.purpose')} onPress={() => router.push('/brokerage/approvals')} />
          <ListRow icon={ScrollText} title={t('brokerage.policies.title')} subtitle={t('brokerage.policies.purpose')} onPress={() => router.push('/brokerage/policies')} />
          <ListRow icon={Wallet} title={t('brokerage.seats.title')} subtitle={t('brokerage.seats.purpose')} onPress={() => router.push('/brokerage/seats')} />
        </>
      ) : (
        <Callout tone="info" title={t('brokerage.roster.notVerified')} action={<Button variant="secondary" label={t('brokerage.verify.title')} onPress={() => router.push('/brokerage/verify')} />} />
      )}
    </Screen>
  );
}
