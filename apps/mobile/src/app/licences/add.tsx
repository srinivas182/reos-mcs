import { router } from 'expo-router';
import { ChevronDown, ShieldCheck } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Pressable, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { addLicence, type CheckResult, checkLicence, formatDay, loadStates, statusWord, type StateOption } from '@/lib/licences';
import { Button, Callout, ChoiceRow, Eyebrow, Icon, Screen, Sheet, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Add a licence (agent journey screen 149): the state, the licence number,
 * and what the state's regulator holds (name, type, status, expiry) with
 * where the check came from. A match in your name is added at once;
 * anything else can be sent to a reviewer, and counts once approved.
 */
export default function AddLicence() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { refresh } = useAuth();
  const [states, setStates] = useState<StateOption[]>([]);
  const [state, setState] = useState<StateOption | null>(null);
  const [picking, setPicking] = useState(false);
  const [number, setNumber] = useState('');
  const [result, setResult] = useState<CheckResult | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    void loadStates().then((list) => {
      setStates(list);
      setState((current) => current ?? list[0] ?? null);
    });
  }, []);

  const check = async () => {
    if (state === null || number.trim() === '') return;

    setBusy(true);
    setProblem(null);
    const answer = await checkLicence(state.code, number.trim());
    setBusy(false);

    if ('error' in answer) {
      setResult(null);
      setProblem(answer.error || t('common.tryAgain'));
    } else {
      setResult(answer);
    }
  };

  const save = async () => {
    if (state === null || result === null) return;

    setBusy(true);
    const saved = await addLicence(state.code, number.trim(), result.check !== 'verified');
    setBusy(false);

    if ('error' in saved) {
      setProblem(saved.error || t('common.tryAgain'));

      return;
    }

    await refresh().catch(() => undefined);
    toast({ message: t(saved.check === 'verified' ? 'mobile.licences.added' : 'mobile.licences.sentForReview', { state: state.name }) });
    router.back();
  };

  const record = result?.record ?? null;
  const verified = result?.check === 'verified';

  return (
    <Screen footer={result ? <Button label={verified ? t('licences.addButton') : t('mobile.licences.sendForReview')} loading={busy} onPress={() => void save()} fullWidth /> : null}>
      <TitleBar title={t('licences.addTitle')} onBack={() => router.back()} />

      <View style={styles.field}>
        <Eyebrow>{t('licences.state')}</Eyebrow>
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${t('licences.state')}: ${state?.name ?? t('mobile.licences.choose')}`}
          onPress={() => setPicking(true)}
          style={({ pressed }) => [styles.picker, pressed && styles.pressed]}
        >
          <Text variant="bodyLarge" weight="semibold" style={styles.pickerText}>
            {state?.name ?? t('mobile.licences.choose')}
          </Text>
          <Icon icon={ChevronDown} />
        </Pressable>
      </View>

      <TextField
        label={t('licences.number')}
        value={number}
        onChangeText={(value) => {
          setNumber(value);
          setResult(null);
          setProblem(null);
        }}
        autoCapitalize="characters"
        autoCorrect={false}
        returnKeyType="search"
        onSubmitEditing={() => void check()}
        maxLength={40}
      />

      {result === null ? <Button label={busy ? t('mobile.licences.checking') : t('mobile.licences.check')} variant="secondary" disabled={busy || number.trim() === '' || state === null} onPress={() => void check()} /> : null}

      {problem ? <Callout tone="danger" title={problem} live /> : null}

      {result && state ? (
        <>
          {verified && record ? (
            <Callout
              tone="success"
              title={t('mobile.licences.found', { name: record.name })}
              body={
                record.expires_on
                  ? t('mobile.licences.foundDetail', { type: t(`licences.types.${record.type}`), status: statusWord(record.status, t), date: formatDay(record.expires_on, i18n.language) })
                  : t('mobile.licences.foundDetailNoDate', { type: t(`licences.types.${record.type}`), status: statusWord(record.status, t) })
              }
              live
            />
          ) : null}
          {result.check === 'name_mismatch' && record ? <Callout tone="warning" title={t('mobile.licences.otherName', { name: record.name })} live /> : null}
          {result.check === 'not_found' ? <Callout tone="warning" title={t('mobile.licences.notFound', { state: state.name })} live /> : null}
          {result.check === 'no_lookup' ? <Callout tone="info" title={t('mobile.licences.noLookup', { state: state.name })} live /> : null}
          {result.source ? (
            <View style={styles.source} accessible>
              <Icon icon={ShieldCheck} color="action" size={18} />
              <Text variant="caption" tone="muted" style={styles.sourceText}>
                {result.source}
              </Text>
            </View>
          ) : null}
        </>
      ) : null}

      <Sheet open={picking} onClose={() => setPicking(false)} title={t('licences.state')}>
        <View accessibilityRole="radiogroup">
          {states.map((option) => (
            <ChoiceRow
              key={option.code}
              title={option.name}
              tile={option.code}
              detail={option.live ? t('mobile.licences.live') : t('licences.comingSoon')}
              selected={option.code === state?.code}
              onPress={() => {
                setState(option);
                setResult(null);
                setPicking(false);
              }}
            />
          ))}
        </View>
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  field: { gap: theme.space.xs },
  picker: {
    flexDirection: 'row',
    alignItems: 'center',
    minHeight: theme.touch + 4,
    paddingHorizontal: theme.space.md,
    borderRadius: theme.m.radius.tile,
    borderWidth: 1,
    borderColor: theme.colors.borderControl,
    backgroundColor: theme.colors.surface,
  },
  pickerText: { flex: 1 },
  pressed: { opacity: 0.85 },
  source: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm, padding: theme.space.md, borderRadius: theme.m.radius.tile, borderWidth: 1, borderColor: theme.colors.border, backgroundColor: theme.colors.surface },
  sourceText: { flex: 1 },
}));
