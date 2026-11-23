import { router, useFocusEffect } from 'expo-router';
import * as WebBrowser from 'expo-web-browser';
import { CalendarCog, GraduationCap, ListTodo, Star, Video } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { useAuth } from '@/lib/auth';
import { activeMembership } from '@/lib/roles';
import { type Booking, cancelBooking, dollars, type InstructorOffer, loadBookings, loadInstructors, rateBooking, slotText } from '@/lib/bookings';
import { Button, Callout, Card, EmptyState, Icon, IconButton, ListRow, Screen, SectionTitle, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * Book an instructor (candidate screen 18, A10.2a): your sessions (join,
 * pay, cancel, rate) and the instructors and mentors taking bookings.
 * Instructors see their bookings here too, and send follow-up tasks.
 */
export default function BookingsScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { user } = useAuth();
  const role = activeMembership(user)?.role;
  const canTeach = role === 'instructor' || role === 'mentor';
  const [bookings, setBookings] = useState<Booking[]>([]);
  const [instructors, setInstructors] = useState<InstructorOffer[] | null>(null);
  const [problem, setProblem] = useState<string | null>(null);
  const reload = useCallback(() => {
    void loadBookings().then(setBookings);
    void loadInstructors().then(setInstructors);
  }, []);

  useFocusEffect(reload);

  const act = async (work: () => Promise<unknown>, done?: string) => {
    setProblem(null);
    try {
      await work();
      if (done) {
        toast({ message: done });
      }
      reload();
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <Screen>
      <TitleBar title={t('bookings.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('bookings.purpose')}</Text>
      {canTeach ? <Button variant="secondary" icon={CalendarCog} label={t('bookings.hours.title')} onPress={() => router.push('/bookings/hours')} /> : null}
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      {bookings.length > 0 ? <SectionTitle>{t('bookings.mine')}</SectionTitle> : null}
      {bookings.map((b) => (
        <Card key={b.uuid}>
          <Text weight="semibold">{t('bookings.with', { name: b.as === 'student' ? b.instructor : b.student })}</Text>
          <Text tone="muted">{`${slotText(b.starts_at, i18n.language)} · ${t('bookings.minutes', { count: b.minutes })} · ${b.topic}`}</Text>
          <Text variant="caption" weight="semibold">
            {t(`bookings.statuses.${b.status}`)}
          </Text>
          <View style={styles.actions}>
            {b.status === 'confirmed' && b.session ? <Button icon={Video} label={t('bookings.join')} onPress={() => router.push({ pathname: '/sessions/[id]', params: { id: b.session ?? '' } })} /> : null}
            {b.status === 'held' && b.checkout_url ? <Button label={t('bookings.pay', { price: dollars(b.price, i18n.language) })} onPress={() => void WebBrowser.openBrowserAsync(b.checkout_url ?? '').then(reload)} /> : null}
            {b.as === 'instructor' && b.status === 'completed' && !b.tasks_sent ? (
              <Button variant="secondary" icon={ListTodo} label={t('bookings.tasks.send')} onPress={() => router.push({ pathname: '/bookings/tasks/[uuid]', params: { uuid: b.uuid, name: b.student } })} />
            ) : null}
            {b.can_cancel ? (
              <Button variant="secondary" label={b.price > 0 && !b.refund_on_cancel ? t('bookings.cancelNoRefund') : t('bookings.cancel')} onPress={() => void act(() => cancelBooking(b.uuid), t('bookings.statuses.cancelled'))} />
            ) : null}
          </View>
          {b.can_rate ? (
            <View style={styles.stars} accessibilityLabel={t('bookings.rate')}>
              <Text>{t('bookings.rate')}</Text>
              {[1, 2, 3, 4, 5].map((n) => (
                <IconButton key={n} icon={Star} label={t('bookings.stars', { count: n })} onPress={() => void act(() => rateBooking(b.uuid, n))} />
              ))}
            </View>
          ) : null}
        </Card>
      ))}

      <SectionTitle>{t('bookings.instructors')}</SectionTitle>
      {instructors !== null && instructors.length === 0 ? <EmptyState icon={<Icon icon={GraduationCap} color="action" size={32} />} title={t('bookings.emptyTitle')} description={t('bookings.empty')} /> : null}
      {(instructors ?? []).map((o) => (
        <ListRow
          key={o.id}
          icon={GraduationCap}
          title={o.name}
          subtitle={[
            o.rating === null ? t('bookings.new') : t('bookings.rating', { rating: o.rating, count: o.ratings }),
            o.price_30 === 0 && o.price_60 === 0 ? t('bookings.included') : t('bookings.from', { price: dollars(Math.min(o.price_30, o.price_60) || Math.max(o.price_30, o.price_60), i18n.language) }),
            o.next_slot ? t('bookings.next', { time: slotText(o.next_slot, i18n.language) }) : null,
          ]
            .filter(Boolean)
            .join(' · ')}
          onPress={() => router.push({ pathname: '/bookings/[id]', params: { id: String(o.user_id) } })}
        />
      ))}
      <Text variant="caption" tone="muted">
        {t('bookings.rules')}
      </Text>
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  actions: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  stars: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: theme.space.xs },
}));
