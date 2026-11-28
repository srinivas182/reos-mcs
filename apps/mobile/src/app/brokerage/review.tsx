import * as WebBrowser from 'expo-web-browser';
import { router, useLocalSearchParams } from 'expo-router';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { clearItem, detailText, loadApprovals, type ApprovalAction, type ApprovalItem } from '@/lib/brokerage';
import { Button, Callout, Card, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar } from '@/ui';

const REASONS = ['unclear', 'money', 'legal', 'asked'] as const;

/**
 * Review one item on the phone (A15.3, broker journey screens 8, 9, 11 and
 * 12): a post with its advertising checks, a public page, or an agent's
 * question with the virtual broker's answer.
 */
export default function ReviewScreen() {
  const { t } = useTranslation();
  const { kind, id } = useLocalSearchParams<{ kind: string; id: string }>();
  const [item, setItem] = useState<ApprovalItem | null>(null);
  const [missing, setMissing] = useState(false);
  const [text, setText] = useState('');
  const [learn, setLearn] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void loadApprovals().then((queue) => {
      const found = queue?.items.find((i) => i.kind === kind && i.id === id) ?? null;
      setItem(found);
      setMissing(found === null);
    });
  }, [kind, id]);

  const act = (current: ApprovalItem, action: ApprovalAction) => {
    setBusy(true);
    setError(null);
    clearItem(current, action, action === 'approve' ? null : text, learn)
      .then(() => router.back())
      .catch((e: Error) => setError(e.message))
      .finally(() => setBusy(false));
  };

  const issues = (current: ApprovalItem): string[] => {
    const v = current.detail.issues;

    return Array.isArray(v)
      ? v.map((i: unknown) => {
          const issue = (i ?? {}) as { phrase?: unknown; fix?: unknown };

          return [typeof issue.phrase === 'string' ? `"${issue.phrase}"` : null, typeof issue.fix === 'string' ? issue.fix : null].filter(Boolean).join(': ');
        })
      : [];
  };
  const reason = (current: ApprovalItem) => REASONS.find((r) => r === current.detail.reason) ?? null;

  return (
    <Screen>
      <TitleBar title={item ? t(`brokerage.approvals.kinds.${item.kind}`) : t('brokerage.approvals.phone.review')} onBack={() => router.back()} />
      {missing ? <Callout tone="info" title={t('brokerage.approvals.emptyTitle')} body={t('brokerage.approvals.empty')} /> : null}
      {error ? <Callout tone="danger" title={error} /> : null}
      {item ? (
        <>
          <Text tone="muted">{t('brokerage.approvals.from', { agent: item.agent })}</Text>
          {item.overdue ? <Callout tone="warning" title={t('brokerage.approvals.overdue')} /> : null}
          <Card>
            {item.kind === 'post' ? (
              <>
                <Text>{detailText(item, 'body') ?? item.summary}</Text>
                <SectionTitle>{t('brokerage.approvals.issues')}</SectionTitle>
                {issues(item).length === 0 ? (
                  <Text tone="muted">{t('brokerage.approvals.noIssues')}</Text>
                ) : (
                  issues(item).map((issue) => (
                    <Text key={issue} tone="danger">
                      {issue}
                    </Text>
                  ))
                )}
                {detailText(item, 'reason') ? <Text>{t('brokerage.approvals.agentReason', { agent: item.agent, reason: detailText(item, 'reason') })}</Text> : null}
              </>
            ) : null}
            {item.kind === 'page' ? (
              <>
                <Text>{detailText(item, 'bio') ?? ''}</Text>
                {detailText(item, 'url') ? <Button variant="secondary" label={t('brokerage.approvals.openPage')} onPress={() => void WebBrowser.openBrowserAsync(detailText(item, 'url') ?? '')} /> : null}
              </>
            ) : null}
            {item.kind === 'question' ? (
              <>
                <Text weight="medium">{detailText(item, 'question') ?? item.summary}</Text>
                {reason(item) ? <Text tone="muted">{`${t('brokerage.approvals.reason')}: ${t(`virtualBroker.reasons.${reason(item) ?? 'asked'}`)}`}</Text> : null}
                {detailText(item, 'answer') ? (
                  <>
                    <SectionTitle>{t('brokerage.approvals.aiAnswer')}</SectionTitle>
                    <Text>{detailText(item, 'answer')}</Text>
                  </>
                ) : null}
              </>
            ) : null}
          </Card>
          <TextField
            label={item.kind === 'question' ? t('brokerage.approvals.answer') : t('brokerage.approvals.note')}
            hint={item.kind === 'question' ? t('brokerage.approvals.answerHint') : t('brokerage.approvals.noteHint')}
            value={text}
            maxLength={4000}
            multiline
            onChangeText={setText}
          />
          {item.kind === 'question' ? (
            <>
              <SwitchRow label={t('brokerage.approvals.addToKnowledge')} value={learn} onValueChange={setLearn} />
              <Button label={t('brokerage.approvals.send')} loading={busy} disabled={text.trim() === ''} onPress={() => act(item, 'answer')} />
            </>
          ) : (
            <>
              <Button label={t('brokerage.approvals.approve')} loading={busy} onPress={() => act(item, 'approve')} />
              <Button variant="secondary" label={t('brokerage.approvals.return')} disabled={busy || text.trim() === ''} onPress={() => act(item, 'return')} />
            </>
          )}
        </>
      ) : null}
    </Screen>
  );
}
