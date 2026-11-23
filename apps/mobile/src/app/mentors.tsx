import { router, useFocusEffect } from 'expo-router';
import { GraduationCap, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { dollars, slotText } from '@/lib/bookings';
import { loadMentors, type MentorList } from '@/lib/licence';
import { Button, EmptyState, Icon, ListRow, Screen, Text, TitleBar } from '@/ui';

/**
 * Your mentors on the phone (candidate f04, A10.3c): the new agent's own
 * brokerage's mentors with rating, languages and next free hour; Book.
 */
export default function MentorsScreen() {
  const { t, i18n } = useTranslation();
  const [list, setList] = useState<MentorList | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadMentors().then(setList);
    }, []),
  );

  return (
    <Screen>
      <TitleBar title={t('mentors.title')} onBack={() => router.back()} />
      {list ? (
        <>
          <Text tone="muted">{list.brokerage ? t('mentors.purpose', { brokerage: list.brokerage }) : t('mentors.purposeNone')}</Text>
          {list.mentors.length === 0 ? (
            <EmptyState
              icon={<Icon icon={Users} color="action" size={32} />}
              title={list.brokerage ? t('mentors.empty') : t('mentors.noBrokerage')}
              description={list.brokerage ? t('mentors.emptyBody', { brokerage: list.brokerage }) : t('mentors.noBrokerageBody')}
            />
          ) : null}
          {list.mentors.map((m) => (
            <ListRow
              key={m.id}
              icon={GraduationCap}
              tone="info"
              title={m.name}
              subtitle={[
                m.rating === null ? t('bookings.new') : t('bookings.rating', { rating: m.rating, count: m.ratings }),
                m.price_30 === 0 && m.price_60 === 0 ? t('bookings.included') : t('bookings.from', { price: dollars(Math.min(m.price_30, m.price_60) || Math.max(m.price_30, m.price_60), i18n.language) }),
                m.languages.map((l) => t(`bookings.languages.${l === 'es' ? 'es' : 'en'}`)).join(', '),
                m.next_slot ? t('bookings.next', { time: slotText(m.next_slot, i18n.language) }) : null,
              ]
                .filter(Boolean)
                .join(' · ')}
              onPress={() => router.push({ pathname: '/bookings/[id]', params: { id: String(m.user_id) } })}
            />
          ))}
          <Button variant="secondary" label={t('mentors.instructors')} onPress={() => router.push('/bookings')} />
        </>
      ) : null}
    </Screen>
  );
}
