import { router, useFocusEffect } from 'expo-router';
import { BadgeCheck, Building2, KeyRound, User } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { checkLicence, dayText, loadSwitch, switchToAgent, type SwitchState } from '@/lib/licence';
import { Button, Callout, Card, Icon, Screen, Text, TextField, TitleBar } from '@/ui';

/**
 * Enter your licence number (candidate f03) and Licensed: switch to agent
 * (20) on the phone, A10.3c: checked against TREC's public record; three
 * ways in on the same account.
 */
export default function LicenceScreen() {
  const { t, i18n } = useTranslation();
  const [state, setState] = useState<SwitchState | null>(null);
  const [number, setNumber] = useState('');
  const [code, setCode] = useState('');
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadSwitch().then((found) => {
        setState(found);
        setNumber(found?.number ?? '');
      });
    }, []),
  );

  const run = async <T,>(work: () => Promise<T>): Promise<T | null> => {
    setBusy(true);
    try {
      const out = await work();
      setProblem(null);

      return out;
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));

      return null;
    } finally {
      setBusy(false);
    }
  };

  const become = async (path: 'broker' | 'association' | 'alone') => {
    if ((await run(() => switchToAgent(path, path === 'association' ? code : null))) !== null) {
      router.replace('/mentors');
    }
  };

  if (!state) {
    return <Screen>{null}</Screen>;
  }

  return (
    <Screen>
      <TitleBar title={t('licence.switch.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('licence.switch.purpose')}</Text>
      {problem ? <Callout tone="danger" title={problem} live /> : null}
      {state.switched ? (
        <Callout tone="success" title={t('licence.switch.done')} action={<Button label={t('licence.switch.mentors')} onPress={() => router.push('/mentors')} />} />
      ) : (
        <Card>
          <TextField label={t('licence.switch.number')} value={number} onChangeText={setNumber} keyboardType="number-pad" maxLength={40} />
          <Button label={t('licence.switch.check')} loading={busy} disabled={number.trim().length < 4} onPress={() => void run(async () => setState(await checkLicence(number.trim())))} />
        </Card>
      )}

      {!state.switched && state.check === 'verified' && state.record ? (
        <Card>
          <View style={styles.row}>
            <Icon icon={BadgeCheck} color="success" />
            <Text weight="semibold">{t('licence.switch.found')}</Text>
          </View>
          <Text>
            {[
              state.record.name,
              t(`licence.switch.types.${state.record.type === 'broker' ? 'broker' : 'sales_agent'}`),
              state.record.licensed_on ? t('licence.switch.issued', { date: dayText(state.record.licensed_on, i18n.language) }) : null,
              state.record.sponsor ? t('licence.switch.sponsor', { name: state.record.sponsor }) : null,
            ]
              .filter(Boolean)
              .join(' · ')}
          </Text>
          <Text tone="muted">{`• ${t('licence.switch.changes.account')}\n• ${t('licence.switch.changes.progress')}\n• ${t('licence.switch.changes.mentors')}`}</Text>
          <Text weight="semibold">{t('licence.switch.ways')}</Text>
          {state.broker ? (
            <Button icon={Building2} label={t('licence.switch.join', { name: state.broker.name })} disabled={busy} onPress={() => void become('broker')} />
          ) : (
            <Text tone="muted">{`${t('licence.switch.broker')}: ${t('licence.switch.noBroker')}`}</Text>
          )}
          <TextField label={t('licence.switch.association')} value={code} onChangeText={setCode} autoCapitalize="characters" maxLength={40} />
          <Button variant="secondary" icon={KeyRound} label={t('licence.switch.useCode')} disabled={busy || code.trim().length < 4} onPress={() => void become('association')} />
          <Button variant="secondary" icon={User} label={`${t('licence.switch.alone')}: ${t('licence.switch.continue')}`} disabled={busy} onPress={() => void become('alone')} />
        </Card>
      ) : null}

      {!state.switched && state.check !== null && state.check !== 'verified' ? (
        <Callout
          tone="info"
          title={t(`licence.switch.results.${state.check}`)}
          body={state.check === 'name_mismatch' && state.record ? t('licence.switch.mismatchBody', { name: state.record.name }) : state.watching ? t('licence.switch.watching') : t('licence.switch.noLookupBody')}
        />
      ) : null}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  row: { flexDirection: 'row', alignItems: 'center', gap: theme.space.sm },
}));
