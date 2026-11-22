import { router } from 'expo-router';
import { FileText, Smartphone, Trash2 } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { biometricAvailable } from '@/lib/auth';
import { enableBiometric } from '@/lib/biometric';
import { openLegal, useConsents, type ConsentRecord } from '@/lib/consents';
import { biometricEnabled, setBiometricEnabled } from '@/lib/session';
import { Button, Callout, Eyebrow, MenuGroup, MenuRow, Screen, SwitchRow, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Privacy and my data (mobile journey screen 153), from Me. The privacy
 * switches and "Your consents" with Withdraw (A6.3); Face ID can be turned
 * off here (screen 189); the terms and privacy notice; Delete account is
 * "Me → Privacy → Delete account" (screen 197).
 */
export default function PrivacyScreen() {
  const { t, i18n } = useTranslation();
  const [biometric, setBiometric] = useState<boolean | null>(null);
  const { summary, failed, change } = useConsents();
  const undo = useUndoToast();
  const [saveFailed, setSaveFailed] = useState(false);

  useEffect(() => {
    void (async () => setBiometric((await biometricAvailable()) ? await biometricEnabled() : null))();
  }, []);

  const toggleBiometric = async (on: boolean) => {
    if (on) {
      setBiometric(await enableBiometric());
    } else {
      await setBiometricEnabled(false);
      setBiometric(false);
    }
  };

  const save = async (purpose: ConsentRecord['purpose'], granted: boolean, subject: string | null = null) => {
    const ok = await change(purpose, granted, subject);
    setSaveFailed(!ok);

    return ok;
  };

  const withdraw = async (record: ConsentRecord) => {
    if ((await save(record.purpose, false, record.subject)) && record.subject === null) {
      undo({
        message: t('consents.withdrawnToast', { purpose: t(`consents.purposes.${record.purpose}.title`) }),
        onUndo: () => void save(record.purpose, true),
      });
    }
  };

  const when = (iso: string) => new Date(iso).toLocaleString(i18n.language, { dateStyle: 'medium', timeStyle: 'short' });

  return (
    <Screen>
      <TitleBar title={t('mobile.privacy.title')} onBack={() => router.back()} />
      {failed || saveFailed ? <Callout tone="danger" title={t('consents.errors.generic')} live /> : null}

      <Eyebrow>{t('consents.switchesTitle')}</Eyebrow>
      <MenuGroup>
        {(summary?.switches ?? []).map((item) => (
          <SwitchRow
            key={item.purpose}
            label={t(`consents.purposes.${item.purpose}.title`)}
            description={`${t(`consents.purposes.${item.purpose}.body`)} ${item.chosen && item.at ? t('consents.chosenOn', { date: when(item.at) }) : t('consents.byDefault')}`}
            value={item.on}
            onValueChange={(on) => void save(item.purpose, on)}
          />
        ))}
      </MenuGroup>

      <Eyebrow>{t('mobile.privacy.security')}</Eyebrow>
      <MenuGroup>
        <MenuRow icon={Smartphone} title={t('mobile.account.devices')} onPress={() => router.push('/devices')} />
        {biometric === null ? null : (
          <SwitchRow label={t('mobile.biometric.setting')} description={t('mobile.biometric.settingHint')} value={biometric} onValueChange={(on) => void toggleBiometric(on)} />
        )}
      </MenuGroup>

      <Eyebrow>{t('consents.history.title')}</Eyebrow>
      <Text tone="muted">{t('consents.history.description')}</Text>
      <View style={styles.records}>
        {(summary?.records ?? []).map((record) => {
          const title = t(`consents.purposes.${record.purpose}.title`);

          return (
            <View key={record.id} style={styles.record} accessible={!record.withdrawable}>
              <Text weight="medium">{record.subject ? `${title} · ${record.subject}` : title}</Text>
              <Text variant="caption" tone="muted">
                {[
                  record.granted ? t('consents.history.agreed') : t('consents.history.withdrawn'),
                  when(record.at),
                  t(`consents.methods.${record.method}`),
                  t('consents.history.version', { version: record.version }),
                ].join(' · ')}
              </Text>
              {record.withdrawable ? (
                <Button
                  variant="secondary"
                  label={t('consents.history.withdraw')}
                  accessibilityLabel={t('consents.history.withdrawFor', { purpose: title })}
                  onPress={() => void withdraw(record)}
                />
              ) : null}
            </View>
          );
        })}
      </View>

      <MenuGroup>
        <MenuRow icon={FileText} title={t('consents.purposes.terms.title')} onPress={() => openLegal('terms')} />
        <MenuRow icon={FileText} title={t('consents.purposes.privacy_notice.title')} onPress={() => openLegal('privacy')} />
      </MenuGroup>
      <MenuGroup>
        <MenuRow icon={Trash2} title={t('mobile.account.delete')} destructive onPress={() => router.push('/delete-account')} />
      </MenuGroup>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  records: { gap: theme.space.md },
  record: {
    gap: theme.space.xs,
    padding: theme.space.lg,
    borderRadius: theme.m.radius.card,
    backgroundColor: theme.colors.surface,
  },
}));
