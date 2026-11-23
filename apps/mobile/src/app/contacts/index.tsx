import { router } from 'expo-router';
import { Sparkles, UserPlus, UserRound, Users } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { addContact, type NewContact, SEGMENTS, type Segment, useContactList } from '@/lib/crm';
import { timeAgo } from '@/lib/time';
import { Button, Chip, EmptyState, Icon, ListRow, Screen, Sheet, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Contacts on the phone (B8.3, Connections and Sync journey): everyone
 * from every CRM and the phone, once, by tab or search, with the AI next
 * step while it's fresh. Opens the contact screen (14). Add contact
 * (B8.3b) saves in REOS and the default CRM (screen 24).
 */
export default function ContactsScreen() {
  const { t, i18n } = useTranslation();
  const [segment, setSegment] = useState<Segment>('all');
  const [search, setSearch] = useState('');
  const { rows, counts, reload } = useContactList(segment, search);
  const toast = useUndoToast();
  const empty: NewContact = { first_name: '', last_name: '', email: '', phone: '' };
  const [adding, setAdding] = useState(false);
  const [person, setPerson] = useState<NewContact>(empty);
  const [saving, setSaving] = useState(false);
  const [failed, setFailed] = useState(false);

  const save = async () => {
    setSaving(true);
    const added = await addContact(person);
    setSaving(false);
    setFailed(added === null);
    if (added) {
      setAdding(false);
      setPerson(empty);
      toast({ message: added.sentTo ? t('crm.hub.addedTo', { tool: added.sentTo }) : t('crm.hub.added') });
      await reload();
      router.push({ pathname: '/contacts/[id]', params: { id: added.id } });
    }
  };

  return (
    <Screen>
      <TitleBar title={t('crm.hub.title')} onBack={() => router.back()} />
      <Button label={t('crm.add.action')} icon={UserPlus} variant="secondary" onPress={() => setAdding(true)} />
      <TextField label={t('crm.hub.searchLabel')} placeholder={t('crm.hub.search')} value={search} onChangeText={setSearch} autoCapitalize="none" />
      <View style={styles.chips} accessibilityRole="tablist" accessibilityLabel={t('crm.hub.tabs')}>
        {SEGMENTS.map((s) => (
          <Chip
            key={s}
            tone={s === segment ? 'info' : 'neutral'}
            label={t('crm.hub.tabCount', { label: t(`crm.hub.segments.${s}`), count: counts[s] ?? 0 })}
            onPress={() => setSegment(s)}
          />
        ))}
      </View>
      {rows !== null && rows.length === 0 ? (
        <EmptyState
          icon={<Icon icon={Users} color="action" size={28} />}
          title={search === '' && segment === 'all' ? t('crm.hub.phoneEmpty.title') : t('crm.hub.emptyFilter.title')}
          description={search === '' && segment === 'all' ? t('crm.hub.phoneEmpty.body') : t('crm.hub.emptyFilter.body')}
        />
      ) : null}
      {(rows ?? []).map((row) => (
        <ListRow
          key={row.id}
          icon={row.next_step ? Sparkles : UserRound}
          tone={row.next_step ? 'info' : 'neutral'}
          title={row.name}
          subtitle={[row.stage ?? t('crm.hub.noStage'), row.last_touch_at ? timeAgo(row.last_touch_at, i18n.language) : t('crm.hub.never'), row.next_step?.text].filter(Boolean).join(' · ')}
          onPress={() => router.push({ pathname: '/contacts/[id]', params: { id: row.id } })}
        />
      ))}
      <Sheet open={adding} onClose={() => setAdding(false)} title={t('crm.add.title')}>
        <Text tone="muted">{t('crm.add.description')}</Text>
        <TextField label={t('crm.add.firstName')} value={person.first_name} onChangeText={(v) => setPerson({ ...person, first_name: v })} />
        <TextField label={t('crm.add.lastName')} value={person.last_name} onChangeText={(v) => setPerson({ ...person, last_name: v })} />
        <TextField label={t('crm.add.email')} value={person.email} onChangeText={(v) => setPerson({ ...person, email: v })} keyboardType="email-address" autoCapitalize="none" />
        <TextField label={t('crm.add.phone')} value={person.phone} onChangeText={(v) => setPerson({ ...person, phone: v })} keyboardType="phone-pad" />
        {failed ? <Text tone="danger">{t('crm.hub.addFailed')}</Text> : null}
        <Button label={t('crm.add.save')} loading={saving} disabled={Object.values(person).every((v) => v.trim() === '')} onPress={() => void save()} />
        <Button variant="ghost" label={t('crm.crms.title')} onPress={() => { setAdding(false); router.push('/connections/crms'); }} />
      </Sheet>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
}));
