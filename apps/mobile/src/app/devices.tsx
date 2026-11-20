import { router } from 'expo-router';
import { ArrowLeft, Smartphone } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { useAuth } from '@/lib/auth';
import { useDevices, type Device } from '@/lib/devices';
import { Chip, IconButton, ListRow, Screen, ScreenHeader, Text } from '@/ui';

export default function DevicesScreen() {
  const { t, i18n } = useTranslation();
  const { signOut } = useAuth();
  const { devices, reload } = useDevices();
  const when = (value: string | null) =>
    value ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '';

  const remove = async (device: Device) => {
    if (device.current) {
      await signOut();

      return;
    }

    await api().DELETE('/me/devices/{device}', { params: { path: { device: device.id } } });
    await reload();
  };

  return (
    <Screen>
      <IconButton icon={ArrowLeft} label={t('common.back')} onPress={() => router.back()} />
      <ScreenHeader title={t('mobile.devices.title')} />
      <View style={rowStyles.list}>
        {devices.map((device) => (
          <ListRow
            key={device.id}
            icon={Smartphone}
            title={device.current ? t('mobile.newDevice.thisPhone', { name: device.name }) : device.name}
            subtitle={device.last_used_at ? t('mobile.devices.lastUsed', { time: when(device.last_used_at) }) : undefined}
            trailing={
              <Chip
                label={t('mobile.devices.signOut')}
                tone="danger"
                accessibilityLabel={`${t('mobile.devices.signOut')}: ${device.name}`}
                onPress={() => void remove(device)}
              />
            }
          />
        ))}
      </View>
      <Text variant="caption" tone="muted">
        {t('mobile.newDevice.emailNote')}
      </Text>
    </Screen>
  );
}

const rowStyles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.md },
}));
