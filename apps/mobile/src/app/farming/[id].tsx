import { router, useLocalSearchParams } from 'expo-router';
import { Send, ShieldCheck } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { checkUpdate, type ComplianceCheck, type FarmDraft, loadDraft, removeArea, sendUpdate, statRows } from '@/lib/farming';
import { Button, Callout, Card, ListRow, Screen, SectionTitle, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * This month's market update for one area (B14.3c): the MLS figures, the
 * update to edit, the compliance check, and sending it.
 */
export default function FarmAreaScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [draft, setDraft] = useState<FarmDraft | null>(null);
  const [subject, setSubject] = useState('');
  const [body, setBody] = useState('');
  const [check, setCheck] = useState<ComplianceCheck | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const money = (n: number) => new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n);
  const day = new Intl.DateTimeFormat(i18n.language, { month: 'short', day: 'numeric' });

  useEffect(() => {
    loadDraft(id)
      .then((d) => {
        setDraft(d);
        setSubject(d.subject);
        setBody(d.body);
        setCheck(d.check);
      })
      .catch((e: Error) => setError(e.message));
  }, [id]);

  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await work();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={draft ? draft.area.name : t('leads.farm.preview')} onBack={() => router.back()} />
      {error ? <Callout tone="danger" title={error} /> : null}

      {draft ? (
        <>
          <SectionTitle>{t('leads.farm.preview')}</SectionTitle>
          <Card>
            {statRows(draft.stats, money).map((row) => (
              <Text key={row.key}>
                {t(`leads.farm.stats.${row.key}`)}: {row.value}
              </Text>
            ))}
            {draft.stats.median_sold_price === null ? <Text tone="muted">{t('leads.farm.soldHidden')}</Text> : null}
          </Card>
          <Card>
            <TextField label={t('leads.farm.subject')} value={subject} maxLength={160} onChangeText={setSubject} />
            <TextField label={t('leads.farm.body')} value={body} maxLength={5000} multiline onChangeText={setBody} hint={draft.attribution} />
          </Card>
          {check?.ok ? <Callout tone="success" title={t('leads.farm.checkOk')} /> : <Callout tone="warning" title={t('leads.farm.checkIssues')} />}
          {(check?.issues ?? []).map((issue, i) => (
            <ListRow key={`${issue.code}-${i}`} icon={ShieldCheck} tone={issue.severity === 'block' ? 'danger' : 'warning'} title={t(`compliance.blocked.${issue.code}`, { phrase: issue.phrase ?? '', citation: issue.citation })} />
          ))}
          <Button variant="secondary" label={t('compliance.check')} loading={busy} onPress={() => void run(async () => setCheck(await checkUpdate(subject, body)))} />
          {!draft.can_send && draft.next_send_at ? <Text tone="muted">{t('leads.farm.nextSend', { date: day.format(new Date(draft.next_send_at)) })}</Text> : null}
          <Button
            icon={Send}
            label={t('leads.farm.send', { count: draft.area.recipients })}
            loading={busy}
            disabled={!draft.can_send || check?.ok !== true || draft.area.recipients === 0}
            onPress={() =>
              void run(async () => {
                setDraft(await sendUpdate(id, subject, body));
                toast({ message: t('leads.farm.sent') });
              })
            }
          />
          <Button
            variant="ghost"
            label={t('leads.farm.remove')}
            disabled={busy}
            onPress={() =>
              void run(async () => {
                await removeArea(id);
                toast({ message: t('leads.farm.removed') });
                router.back();
              })
            }
          />
        </>
      ) : null}
    </Screen>
  );
}
