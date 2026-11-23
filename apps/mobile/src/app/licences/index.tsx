import { router } from 'expo-router';
import { Circle, CircleCheck, FileUp, IdCard, Plus } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { BROKER_ROLES, type BrokerRole, formatDay, type Licence, licenceLine, removeLicence, restoreLicence, setBrokerRole, useLicences } from '@/lib/licences';
import { Button, Card, Chip, EmptyState, Icon, IconButton, PillGroup, ProgressBar, Screen, Sheet, Text, TitleBar, useUndoToast } from '@/ui';

const NONE = 'none';

/**
 * My licences (agent journey screen 148): each state licence with its type,
 * number, status, how it was checked, sponsoring broker and renewal date.
 * States that aren't live are explained (tracked for renewal only). Tap a
 * licence for who you supervise and Remove (with Undo); + adds one.
 */
export default function MyLicences() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { refresh } = useAuth();
  const { licences, reload } = useLicences();
  const [open, setOpen] = useState<Licence | null>(null);

  const changed = async () => {
    await reload();
    await refresh().catch(() => undefined);
  };

  const remove = async (licence: Licence) => {
    setOpen(null);

    if (await removeLicence(licence.id)) {
      await changed();
      toast({
        message: t('licences.removed', { state: licence.state.name }),
        onUndo: async () => {
          await restoreLicence(licence.id);
          await changed();
        },
      });
    }
  };

  const chooseRole = async (licence: Licence, value: string) => {
    const role = value === NONE ? null : (value as BrokerRole);

    if (await setBrokerRole(licence.id, role)) {
      setOpen({ ...licence, broker_role: role });
      await reload();
    }
  };

  return (
    <Screen>
      <TitleBar title={t('mobile.licences.title')} onBack={() => router.back()} action={<IconButton icon={Plus} primary label={t('mobile.licences.add')} onPress={() => router.push('/licences/add')} />} />

      {licences !== null && licences.length === 0 ? (
        <EmptyState
          icon={<Icon icon={IdCard} color="action" size={32} />}
          title={t('mobile.licences.emptyTitle')}
          description={t('mobile.licences.emptyBody')}
          action={<Button label={t('mobile.licences.add')} onPress={() => router.push('/licences/add')} />}
        />
      ) : null}

      {(licences ?? []).map((licence) => (
        <Card key={licence.id} onPress={() => setOpen(licence)} accessibilityLabel={`${t('licences.title', { state: licence.state.name, type: t(`licences.types.${licence.type}`) })}. ${licenceLine(licence, t, i18n.language)}`} style={licence.tracked_only ? undefined : styles.liveCard}>
          <View style={styles.head}>
            <View style={[styles.tile, licence.tracked_only && styles.tileMuted]}>
              <Text weight="bold" style={styles.tileText}>
                {licence.state.code}
              </Text>
            </View>
            <View style={styles.titles}>
              <Text variant="bodyLarge" weight="semibold">
                {t('licences.title', { state: licence.state.name, type: t(`licences.types.${licence.type}`) })}
              </Text>
              <Text variant="caption" tone="muted">
                {licenceLine(licence, t, i18n.language)}
              </Text>
            </View>
            <Chip label={licence.tracked_only ? t('licences.comingSoon') : t('mobile.licences.live')} tone={licence.tracked_only ? 'neutral' : 'success'} />
          </View>

          {licence.tracked_only ? (
            <Text tone="muted">{t('licences.trackedOnly', { state: licence.state.name })}</Text>
          ) : (
            <View style={styles.details}>
              {licence.sponsor_name ? <Row label={t('licences.sponsor')} value={licence.sponsor_name} /> : null}
              {licence.ce ? <Row label={t('licences.ceThisCycle')} value={t('mobile.licences.ceHours', { done: licence.ce.hours_done, required: licence.ce.hours_required })} /> : null}
              {licence.expires_on ? <Row label={t('licences.renewal')} value={formatDay(licence.expires_on, i18n.language) ?? ''} /> : null}
            </View>
          )}

          {licence.status === 'pending_review' ? <Text tone="muted">{t('licences.waiting', { note: licence.review_note ?? '' })}</Text> : null}
          {licence.status === 'rejected' ? <Text tone="danger">{t('licences.rejectedNote', { note: licence.review_note ?? '' })}</Text> : null}
        </Card>
      ))}

      {/* CE taken elsewhere: read by OCR, checked, saved (B10.3b). */}
      {licences !== null && licences.length > 0 ? (
        <Button variant="secondary" icon={FileUp} label={t('certificates.add')} onPress={() => router.push('/licences/certificate')} />
      ) : null}

      {licences !== null && licences.length > 0 ? (
        <Text variant="caption" tone="muted">
          {t('mobile.licences.footer')}
        </Text>
      ) : null}

      <Sheet open={open !== null} onClose={() => setOpen(null)} title={open ? t('mobile.licences.detailTitle', { state: open.state.name }) : ''}>
        {open ? (
          <>
            <Text tone="muted">{licenceLine(open, t, i18n.language)}</Text>
            {open.ce && !open.tracked_only ? <CeNeeds ce={open.ce} /> : null}
            {open.tracked_only ? null : (
              <PillGroup
                label={t('licences.role')}
                value={open.broker_role ?? NONE}
                options={[
                  { value: NONE, label: t('licences.roles.none') },
                  ...BROKER_ROLES.filter((role) => open.type === 'broker' || role === 'delegated_supervisor').map((role) => ({ value: role, label: t(`licences.roles.${role}`) })),
                ]}
                onChange={(value) => void chooseRole(open, value)}
              />
            )}
            <Button label={t('licences.remove', { state: open.state.name })} variant="secondary" onPress={() => void remove(open)} />
          </>
        ) : null}
      </Sheet>
    </Screen>
  );
}

/** "What you still need" (A5.2): each required course and the electives, done or not, with the deferral option and the rules' source. */
function CeNeeds({ ce }: { ce: NonNullable<Licence['ce']> }) {
  const { t, i18n } = useTranslation();

  return (
    <View style={styles.needs}>
      <Text weight="semibold">{ce.complete ? t('ce.done') : t('ce.left', { hours: ce.hours_left, date: formatDay(ce.due_on, i18n.language) ?? '' })}</Text>
      <ProgressBar value={ce.hours_required > 0 ? Math.round((ce.hours_done / ce.hours_required) * 100) : 0} label={t('ce.progress')} />
      {ce.first_renewal ? <Text tone="muted">{t('ce.firstRenewal')}</Text> : null}
      {ce.parts.map((part) => (
        <View key={part.code} style={styles.part} accessible accessibilityLabel={`${part.title}: ${t('ce.partHours', { done: part.hours_done, hours: part.hours })}${part.done ? `, ${t('ce.partDone')}` : ''}`}>
          <Icon icon={part.done ? CircleCheck : Circle} color={part.done ? 'success' : 'textMuted'} size={20} />
          <Text style={styles.partTitle}>{part.title}</Text>
          <Text weight="semibold">{t('ce.partHours', { done: part.hours_done, hours: part.hours })}</Text>
        </View>
      ))}
      {ce.deferral && !ce.complete ? <Text tone="muted">{t('ce.deferral', { fee: ce.deferral.fee_usd, days: ce.deferral.days })}</Text> : null}
      <Text variant="caption" tone="muted">
        {t('ce.sources', { sources: [...new Set(ce.sources.map((s) => s.citation))].join('; ') })}
      </Text>
    </View>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row} accessible accessibilityLabel={`${label}: ${value}`}>
      <Text tone="muted">{label}</Text>
      <Text weight="semibold">{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  liveCard: { borderColor: theme.colors.action, borderWidth: 2 },
  head: { flexDirection: 'row', alignItems: 'center', gap: theme.space.md },
  tile: { width: theme.touch, height: theme.touch, borderRadius: theme.m.radius.tile, backgroundColor: theme.colors.heroBg, alignItems: 'center', justifyContent: 'center' },
  tileMuted: { backgroundColor: theme.colors.textMuted },
  tileText: { color: theme.colors.heroText },
  titles: { flex: 1 },
  details: { borderRadius: theme.m.radius.tile, borderWidth: 1, borderColor: theme.colors.border, paddingHorizontal: theme.space.md },
  needs: { gap: theme.space.sm },
  part: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, minHeight: theme.touch - 8 },
  partTitle: { flex: 1 },
  row: { flexDirection: 'row', justifyContent: 'space-between', gap: theme.space.md, minHeight: theme.touch, alignItems: 'center' },
}));
