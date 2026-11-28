import { router } from 'expo-router';
import { Send } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { blankReferral, canRefer, type ReferralForm, sendReferral } from '@/lib/referrals';
import { Button, Callout, Card, OptionGroup, Screen, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Refer a client (B14.3b): to an agent on REOS (they accept in the app)
 * or outside it (they get an email). The fee is what the brokers agree.
 */
export default function NewReferralScreen() {
  const { t } = useTranslation();
  const toast = useUndoToast();
  const [form, setForm] = useState<ReferralForm>(blankReferral);
  const [agreed, setAgreed] = useState(false);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const set = (key: keyof ReferralForm) => (value: string) => setForm((now) => ({ ...now, [key]: value }));

  const send = async () => {
    setBusy(true);
    setError(null);
    try {
      await sendReferral(form);
      toast({ message: t('leads.referrals.done') });
      router.back();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  return (
    <Screen>
      <TitleBar title={t('leads.referrals.send')} onBack={() => router.back()} />
      {error ? <Callout tone="danger" title={error} /> : null}
      <Card>
        <TextField label={t('leads.referrals.toName')} value={form.toName} maxLength={120} autoComplete="name" onChangeText={set('toName')} />
        <TextField label={t('leads.referrals.toEmail')} value={form.toEmail} maxLength={160} keyboardType="email-address" autoCapitalize="none" onChangeText={set('toEmail')} />
        <TextField label={t('leads.referrals.toBrokerage')} value={form.toBrokerage} maxLength={160} onChangeText={set('toBrokerage')} />
      </Card>
      <Card>
        <TextField label={t('leads.referrals.clientName')} value={form.clientName} maxLength={120} onChangeText={set('clientName')} />
        <TextField label={t('leads.referrals.clientEmail')} value={form.clientEmail} maxLength={160} keyboardType="email-address" autoCapitalize="none" onChangeText={set('clientEmail')} />
        <TextField label={t('leads.referrals.clientPhone')} value={form.clientPhone} maxLength={30} keyboardType="phone-pad" onChangeText={set('clientPhone')} />
        <OptionGroup label={t('leads.referrals.kind')} options={(['buyer', 'seller'] as const).map((value) => ({ value, label: t(`leads.referrals.kinds.${value}`) }))} value={form.kind} onChange={(kind) => setForm((now) => ({ ...now, kind }))} />
        <TextField label={t('leads.referrals.area')} value={form.area} maxLength={120} onChangeText={set('area')} hint={t('leads.referrals.areaHint')} />
        <TextField label={t('leads.referrals.noteLabel')} value={form.note} maxLength={1000} multiline onChangeText={set('note')} />
        <TextField label={t('leads.referrals.fee')} value={form.fee} maxLength={2} keyboardType="number-pad" onChangeText={set('fee')} hint={t('leads.referrals.feeHint')} />
      </Card>
      <SwitchRow label={t('leads.referrals.agreed')} value={agreed} onValueChange={setAgreed} />
      <Text tone="muted">{t('leads.referrals.feeHint')}</Text>
      <Button icon={Send} label={t('leads.referrals.send')} loading={busy} disabled={!canRefer(form, agreed)} onPress={() => void send()} />
    </Screen>
  );
}
