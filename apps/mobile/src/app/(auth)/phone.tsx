import { router } from 'expo-router';
import { ArrowLeft } from 'lucide-react-native';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { useAuth } from '@/lib/auth';
import { normalisePhone } from '@/lib/phone';
import { Button, IconButton, Screen, ScreenHeader, Text, TextField } from '@/ui';

/** Phone sign-in, step 1: the number. */
export default function PhoneScreen() {
  const { t } = useTranslation();
  const { sendPhoneCode } = useAuth();
  const [value, setValue] = useState('');
  const [error, setError] = useState<string | undefined>();
  const [busy, setBusy] = useState(false);

  const send = async () => {
    const phone = normalisePhone(value);

    if (phone === null) {
      setError(t('mobile.signIn.phoneInvalid'));

      return;
    }

    setBusy(true);
    const result = await sendPhoneCode(phone);
    setBusy(false);

    if (result.ok) {
      router.push({ pathname: '/code', params: { phone } });
    } else {
      setError(result.message);
    }
  };

  return (
    <Screen footer={<Button label={t('mobile.signIn.sendCode')} loading={busy} fullWidth onPress={() => void send()} />}>
      <IconButton icon={ArrowLeft} label={t('common.back')} onPress={() => router.back()} />
      <ScreenHeader title={t('mobile.signIn.phoneTitle')} />
      <Text tone="muted">{t('mobile.signIn.phoneHint')}</Text>
      <TextField
        label={t('mobile.signIn.phoneLabel')}
        value={value}
        onChangeText={(text) => {
          setValue(text);
          setError(undefined);
        }}
        error={error}
        keyboardType="phone-pad"
        textContentType="telephoneNumber"
        autoComplete="tel"
        autoFocus
        onSubmitEditing={() => void send()}
        placeholder="+1 512 555 0100"
      />
    </Screen>
  );
}
