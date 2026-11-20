import { router } from 'expo-router';
import { Bell, CheckCircle2, ScanFace, Smartphone } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { Linking, View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { biometricAvailable } from '@/lib/auth';
import { enableBiometric } from '@/lib/biometric';
import { useDevices, type Device } from '@/lib/devices';
import { biometricEnabled } from '@/lib/session';
import { Button, Callout, Chip, ListRow, Screen, ScreenHeader, SectionTitle, Text } from '@/ui';


/** First sign-in on a phone (journey screen SH/newdevice): what moved, what to set up, other devices. */
export default function NewDeviceScreen() {
  const { t } = useTranslation();
  const { devices, reload } = useDevices();
  const [faceId, setFaceId] = useState<'unavailable' | 'off' | 'on'>('unavailable');

  useEffect(() => {
    void (async () => {
      if (await biometricAvailable()) {
        setFaceId((await biometricEnabled()) ? 'on' : 'off');
      }
    })();
  }, []);

  const signOutOther = async (device: Device) => {
    await api().DELETE('/me/devices/{device}', { params: { path: { device: device.id } } });
    await reload();
  };

  return (
    <Screen footer={<Button label={t('mobile.newDevice.done')} fullWidth onPress={() => router.replace('/')} />}>
      <ScreenHeader title={t('mobile.newDevice.title')} />
      <Callout tone="info" title={t('mobile.newDevice.synced')} />
      <View style={rowStyles.list}>
        {faceId === 'unavailable' ? null : (
          <ListRow
            icon={ScanFace}
            title={t('mobile.newDevice.faceId')}
            trailing={
              faceId === 'on' ? (
                <Chip label={t('mobile.newDevice.on')} tone="success" icon={CheckCircle2} />
              ) : (
                <Chip label={t('mobile.newDevice.setUp')} tone="info" onPress={() => void enableBiometric().then((ok) => ok && setFaceId('on'))} />
              )
            }
          />
        )}
        <ListRow icon={Bell} title={t('mobile.newDevice.notifications')} trailing={<Chip label={t('mobile.newDevice.turnOn')} tone="info" onPress={() => void Linking.openSettings()} />} />
      </View>
      {devices.length > 0 ? <SectionTitle>{t('mobile.newDevice.yourDevices')}</SectionTitle> : null}
      <View style={rowStyles.list}>
        {devices.map((device) => (
          <ListRow
            key={device.id}
            icon={Smartphone}
            title={device.current ? t('mobile.newDevice.thisPhone', { name: device.name }) : device.name}
            trailing={
              device.current ? (
                <Text tone="muted">{t('mobile.newDevice.now')}</Text>
              ) : (
                <Chip label={t('mobile.devices.signOut')} tone="danger" accessibilityLabel={`${t('mobile.devices.signOut')}: ${device.name}`} onPress={() => void signOutOther(device)} />
              )
            }
          />
        ))}
      </View>
      <View style={styles.note}>
        <Text variant="caption" tone="muted">
          {t('mobile.newDevice.emailNote')}
        </Text>
      </View>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  note: { paddingHorizontal: theme.space.xs },
}));

const rowStyles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
}));
