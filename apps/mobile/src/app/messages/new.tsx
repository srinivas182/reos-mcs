import { router } from 'expo-router';
import { useEffect, useMemo, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type Contact, fetchContacts, startConversation } from '@/lib/messages';
import { Button, Callout, ChoiceRow, Screen, Text, TextField, TitleBar } from '@/ui';

/**
 * New message on the phone (B6.1): pick people you work with (several make
 * a group of up to 20, optionally named) and write the first message.
 */
export default function NewMessageScreen() {
  const { t } = useTranslation();
  const [contacts, setContacts] = useState<Contact[] | null>(null);
  const [query, setQuery] = useState('');
  const [chosen, setChosen] = useState<number[]>([]);
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [busy, setBusy] = useState(false);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    void fetchContacts().then(setContacts).catch(() => setContacts([]));
  }, []);

  const shown = useMemo(() => (contacts ?? []).filter((c) => `${c.name} ${c.organisation}`.toLowerCase().includes(query.trim().toLowerCase())), [contacts, query]);

  const send = async () => {
    setBusy(true);
    const id = await startConversation(chosen, body.trim(), chosen.length > 1 ? title : null).catch(() => null);
    setBusy(false);
    setFailed(id === null);

    if (id !== null) router.replace({ pathname: '/messages/[id]', params: { id } });
  };

  return (
    <Screen footer={<Button label={t('messages.send')} loading={busy} disabled={chosen.length === 0 || body.trim() === ''} fullWidth onPress={() => void send()} />}>
      <TitleBar title={t('messages.new')} onBack={() => router.back()} />
      <Text tone="muted">{t('messages.newDescription')}</Text>
      {failed ? <Callout tone="danger" title={t('messages.errors.send')} live /> : null}
      <TextField label={t('messages.search')} value={query} onChangeText={setQuery} autoCapitalize="none" />
      <View style={styles.list}>
        {contacts === null ? <Text tone="muted">{t('messages.loading')}</Text> : null}
        {contacts !== null && shown.length === 0 ? <Text tone="muted">{contacts.length === 0 ? t('messages.noContacts') : t('messages.noMatch')}</Text> : null}
        {shown.map((contact) => (
          <ChoiceRow
            key={contact.id}
            title={contact.name}
            detail={`${t(`roles.names.${contact.role}` as 'roles.names.agent')} · ${contact.organisation}`}
            selected={chosen.includes(contact.id)}
            onPress={() => setChosen(chosen.includes(contact.id) ? chosen.filter((c) => c !== contact.id) : [...chosen, contact.id])}
          />
        ))}
      </View>
      {chosen.length > 1 ? <TextField label={t('messages.groupName')} value={title} onChangeText={setTitle} maxLength={120} /> : null}
      <TextField label={t('messages.write')} value={body} onChangeText={setBody} multiline maxLength={4000} />
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  list: { gap: theme.space.sm },
}));
