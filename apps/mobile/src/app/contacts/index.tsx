import { router } from 'expo-router';
import { Sparkles, UserRound, Users } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { SEGMENTS, type Segment, useContactList } from '@/lib/crm';
import { timeAgo } from '@/lib/time';
import { Chip, EmptyState, Icon, ListRow, Screen, TextField, TitleBar } from '@/ui';

/**
 * Contacts on the phone (B8.3, Connections and Sync journey): everyone
 * from every CRM and the phone, once, by tab or search, with the AI next
 * step while it's fresh. Opens the contact screen (14).
 */
export default function ContactsScreen() {
  const { t, i18n } = useTranslation();
  const [segment, setSegment] = useState<Segment>('all');
  const [search, setSearch] = useState('');
  const { rows, counts } = useContactList(segment, search);

  return (
    <Screen>
      <TitleBar title={t('crm.hub.title')} onBack={() => router.back()} />
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
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.xs },
}));
