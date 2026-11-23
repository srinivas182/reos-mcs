/**
 * Share your news on the phone (f05, A10.3b): who hears is the candidate's
 * choice; first name and city only, an optional tip, never scores. Also
 * the broker offers opt-in from the tracker (screen 17).
 */
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { Button, Card, SwitchRow, Text, TextField } from '@/ui';

export type ShareChoice = { to_group: boolean; to_school: boolean; to_state: boolean; city: string | null; tip: string | null };

export function ShareNews({ state, city: initialCity, onShare, onSkip }: { state: string; city: string | null; onShare: (choice: ShareChoice) => void; onSkip: () => void }) {
  const { t } = useTranslation();
  const [toGroup, setToGroup] = useState(true);
  const [toSchool, setToSchool] = useState(true);
  const [toState, setToState] = useState(true);
  const [city, setCity] = useState(initialCity ?? '');
  const [tip, setTip] = useState('');

  return (
    <Card>
      <Text weight="semibold">{t('licence.news.title')}</Text>
      <Text tone="muted">{t('licence.news.body')}</Text>
      <SwitchRow label={t('licence.news.group')} description={t('licence.news.groupHint')} value={toGroup} onValueChange={setToGroup} />
      <SwitchRow label={t('licence.news.school')} description={t('licence.news.schoolHint')} value={toSchool} onValueChange={setToSchool} />
      <SwitchRow label={t('licence.news.state', { state })} description={t('licence.news.stateHint')} value={toState} onValueChange={setToState} />
      <TextField label={t('licence.news.city')} value={city} onChangeText={setCity} maxLength={120} />
      <TextField label={t('licence.news.tip')} placeholder={t('licence.news.tipPlaceholder')} value={tip} onChangeText={setTip} maxLength={280} multiline />
      <View style={styles.actions}>
        <Button
          label={t('licence.news.share')}
          disabled={!toGroup && !toSchool && !toState}
          onPress={() => onShare({ to_group: toGroup, to_school: toSchool, to_state: toState, city: city.trim() || null, tip: tip.trim() || null })}
        />
        <Button variant="secondary" label={t('licence.news.notNow')} onPress={onSkip} />
      </View>
    </Card>
  );
}

export function OffersOptIn({
  state,
  offers,
  onSave,
  onSee,
}: {
  state: string;
  offers: { opted_in: boolean; city: string | null; open: number };
  onSave: (on: boolean, city: string | null) => void;
  onSee: () => void;
}) {
  const { t } = useTranslation();
  const [on, setOn] = useState(offers.opted_in);
  const [city, setCity] = useState(offers.city ?? '');

  return (
    <Card>
      <Text weight="semibold">{t('licence.offers.title')}</Text>
      <SwitchRow label={t('licence.offers.optIn')} description={t('licence.offers.optInHint', { state })} value={on} onValueChange={setOn} />
      {on ? <TextField label={t('licence.offers.city')} value={city} onChangeText={setCity} maxLength={120} /> : null}
      <View style={styles.actions}>
        <Button variant="secondary" label={t('licence.offers.save')} disabled={on === offers.opted_in && (city.trim() || null) === offers.city} onPress={() => onSave(on, city.trim() || null)} />
        {offers.opted_in ? <Button label={offers.open > 0 ? t('licence.offers.open', { count: offers.open }) : t('licence.offers.see')} onPress={onSee} /> : null}
      </View>
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  actions: { flexDirection: 'row', gap: theme.space.sm, flexWrap: 'wrap' },
}));
