import { router, useLocalSearchParams } from 'expo-router';
import { CircleCheck } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useAuth } from '@/lib/auth';
import { canSave, saveVisit, sendPending, type Timeline, TIMELINES } from '@/lib/openHouses';
import { Button, Card, EmptyState, Icon, OptionGroup, Screen, SwitchRow, Text, TextField } from '@/ui';

/**
 * Sign-in on the agent's phone (kiosk mode, B14.3a): visitors type their
 * details; each sign-in is saved on the phone at once and sent when it's
 * online, so no signal at the house loses nobody. The agent's name and
 * the consent line are on the form, as on the QR sign-in page.
 */
export default function KioskScreen() {
  const { t } = useTranslation();
  const { id } = useLocalSearchParams<{ id: string }>();
  const { user } = useAuth();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [timeline, setTimeline] = useState<Timeline | null>(null);
  const [hasAgent, setHasAgent] = useState(false);
  const [preApproved, setPreApproved] = useState(false);
  const [consent, setConsent] = useState(false);
  const [saved, setSaved] = useState(false);
  const [waiting, setWaiting] = useState(0);

  const reset = () => {
    setName('');
    setEmail('');
    setPhone('');
    setTimeline(null);
    setHasAgent(false);
    setPreApproved(false);
    setConsent(false);
    setSaved(false);
  };

  const signIn = () => {
    if (!consent) return;
    setWaiting(saveVisit(id, { name, email: email.trim() === '' ? null : email, phone: phone.trim() === '' ? null : phone, has_agent: hasAgent, pre_approved: preApproved, timeline, consent: true }));
    setSaved(true);
    void sendPending(id).then(setWaiting);
  };

  const footer = (
    <>
      {waiting > 0 ? <Text tone="muted">{t('leads.openHouse.waiting', { count: waiting })}</Text> : null}
      <Button variant="ghost" label={t('leads.openHouse.done')} onPress={() => router.back()} />
    </>
  );

  if (saved)
    return (
      <Screen footer={footer}>
        <EmptyState icon={<Icon icon={CircleCheck} color="success" size={48} />} title={t('leads.openHouse.saved')} description={t('leads.openHouse.thanks')} action={<Button label={t('leads.openHouse.next')} onPress={reset} />} />
      </Screen>
    );

  return (
    <Screen footer={footer}>
      <Text variant="title" heading>
        {t('leads.openHouse.welcome')}
      </Text>
      <Card>
        <TextField label={t('leads.form.name')} value={name} maxLength={120} autoComplete="name" onChangeText={setName} />
        <TextField label={t('leads.form.email')} value={email} maxLength={160} keyboardType="email-address" autoCapitalize="none" autoComplete="email" onChangeText={setEmail} />
        <TextField label={t('leads.form.phone')} value={phone} maxLength={30} keyboardType="phone-pad" autoComplete="tel" onChangeText={setPhone} hint={t('leads.form.oneOf')} />
        <OptionGroup label={t('leads.form.timeline')} options={TIMELINES.map((value) => ({ value, label: t(`leads.timelines.${value}`) }))} value={timeline} onChange={setTimeline} />
        <SwitchRow label={t('leads.form.hasAgent')} value={hasAgent} onValueChange={setHasAgent} />
        <SwitchRow label={t('leads.form.preApproved')} value={preApproved} onValueChange={setPreApproved} />
        <SwitchRow label={t('leads.form.consent', { agent: user?.name ?? '' })} value={consent} onValueChange={setConsent} />
        <Button label={t('leads.openHouse.signIn')} disabled={!canSave({ name, email, phone, consent })} onPress={signIn} />
      </Card>
    </Screen>
  );
}
