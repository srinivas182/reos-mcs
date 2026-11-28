import { router, useFocusEffect } from 'expo-router';
import { Gift, Handshake, Send, Share2, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { Share, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { answerReferral, claimInvite, type InviteSummary, loadInvite, loadReferrals, type Referral } from '@/lib/referrals';
import { activeMembership } from '@/lib/roles';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Referrals and invites on the phone (B14.3b, ADR 0067): referrals sent to
 * the agent (accept into contacts, or decline), the ones they sent, and
 * their invite link (points when someone they invited gets licensed).
 */
export default function ReferralsScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { user } = useAuth();
  const agent = activeMembership(user)?.role === 'agent';
  const [referrals, setReferrals] = useState<{ sent: Referral[]; received: Referral[] } | null>(null);
  const [invite, setInvite] = useState<InviteSummary | null>(null);
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const points = new Intl.NumberFormat(i18n.language);

  useFocusEffect(
    useCallback(() => {
      void loadInvite().then(setInvite);
      if (agent) void loadReferrals().then(setReferrals);
    }, [agent]),
  );

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

  const answer = (r: Referral, how: 'accept' | 'decline' | 'close') =>
    run(async () => {
      const next = await answerReferral(r.id, how);
      setReferrals((now) => (now ? { sent: now.sent.map((x) => (x.id === next.id ? next : x)), received: now.received.map((x) => (x.id === next.id ? next : x)) } : now));
      if (how === 'accept') toast({ message: t('leads.referrals.acceptedToast') });
    });

  const line = (r: Referral) =>
    [
      r.direction === 'sent' ? t('leads.referrals.toLine', { name: [r.to.name, r.to.broker].filter(Boolean).join(', ') }) : t('leads.referrals.fromLine', { name: [r.from.name, r.from.broker].filter(Boolean).join(', ') }),
      t(`leads.referrals.statuses.${r.status}`),
      r.fee_percent === null ? null : t('leads.referrals.feeLine', { fee: r.fee_percent }),
    ]
      .filter(Boolean)
      .join(' · ');

  const card = (r: Referral) => (
    <Card key={r.id}>
      <Text weight="semibold">{[r.client.name, t(`leads.referrals.kinds.${r.kind}`), r.area].filter(Boolean).join(' · ')}</Text>
      <Text tone="muted">{line(r)}</Text>
      {r.note ? <Text>{r.note}</Text> : null}
      {r.direction === 'received' && r.status === 'sent' ? (
        <View style={styles.row}>
          <Button label={t('leads.referrals.accept')} loading={busy} onPress={() => void answer(r, 'accept')} />
          <Button variant="secondary" label={t('leads.referrals.decline')} disabled={busy} onPress={() => void answer(r, 'decline')} />
        </View>
      ) : null}
      {r.status === 'accepted' || r.status === 'emailed' ? <Button variant="ghost" label={t('leads.referrals.close')} disabled={busy} onPress={() => void answer(r, 'close')} /> : null}
      {r.contact ? <Button variant="secondary" icon={UserRound} label={t('leads.referrals.openContact')} onPress={() => router.push({ pathname: '/contacts/[id]', params: { id: r.contact ?? '' } })} /> : null}
    </Card>
  );

  return (
    <Screen>
      <TitleBar title={t('leads.referrals.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('leads.referrals.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      {agent ? <Button icon={Send} label={t('leads.referrals.send')} onPress={() => router.push('/referrals/new')} /> : null}
      {agent && referrals && referrals.sent.length === 0 && referrals.received.length === 0 ? (
        <EmptyState icon={<Icon icon={Handshake} color="action" size={32} />} title={t('leads.referrals.none')} description={t('leads.referrals.noneHint')} />
      ) : null}
      {referrals && referrals.received.length > 0 ? <SectionTitle>{t('leads.referrals.received')}</SectionTitle> : null}
      {(referrals?.received ?? []).map(card)}
      {referrals && referrals.sent.length > 0 ? <SectionTitle>{t('leads.referrals.sent')}</SectionTitle> : null}
      {(referrals?.sent ?? []).map(card)}

      {invite ? (
        <>
          <SectionTitle>{t('leads.invites.title')}</SectionTitle>
          <Card>
            <Text>{t('leads.invites.purpose', { points: points.format(invite.points_each) })}</Text>
            <Text weight="semibold">{t('leads.invites.codeLine', { code: invite.code })}</Text>
            <Text tone="muted">{invite.link}</Text>
            <Button icon={Share2} label={t('listings.post.share')} onPress={() => void Share.share({ message: invite.link })} />
            <Text tone="muted">
              {t('leads.invites.earned', { points: points.format(invite.earned) })} · {t('leads.invites.pointsNote')}
            </Text>
          </Card>
          {invite.invited.length === 0 ? <EmptyState icon={<Icon icon={Gift} color="action" size={32} />} title={t('leads.invites.noneInvited')} description={t('leads.invites.noneHint')} /> : null}
          {invite.invited.map((p) => (
            <ListRow key={`${p.name}-${p.joined_at}`} icon={UserRound} tone={p.licensed ? 'success' : 'neutral'} title={p.name} subtitle={p.licensed ? t('leads.invites.licensed') : t('leads.invites.notYet')} />
          ))}
          {invite.invited_by ? <Text tone="muted">{t('leads.invites.invitedBy', { name: invite.invited_by })}</Text> : null}
          {invite.can_claim ? (
            <Card>
              <Text weight="semibold">{t('leads.invites.claimTitle')}</Text>
              <TextField label={t('leads.invites.code')} value={code} maxLength={8} autoCapitalize="characters" autoCorrect={false} onChangeText={setCode} hint={t('leads.invites.claimHint')} />
              <Button
                label={t('leads.invites.claim')}
                loading={busy}
                disabled={code.trim().length !== 8}
                onPress={() =>
                  void run(async () => {
                    setInvite(await claimInvite(code));
                    setCode('');
                    toast({ message: t('leads.invites.claimed') });
                  })
                }
              />
            </Card>
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
}));
