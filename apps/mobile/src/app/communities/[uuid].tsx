import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { CalendarDays, MessageSquare } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Linking } from 'react-native';

import { cancelRsvp, loadCommunity, postTo, rsvp, type CommunitySession, type CommunityView } from '@/lib/communities';
import { Button, Callout, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TextField, TitleBar } from '@/ui';

/**
 * A community on the phone (B17.2, provider screen 17): sessions with RSVP
 * and the CE note, posts checked before they go up, and resources.
 */
export default function CommunityScreen() {
  const { t, i18n } = useTranslation();
  const { uuid } = useLocalSearchParams<{ uuid: string }>();
  const [community, setCommunity] = useState<CommunityView | null>(null);
  const [body, setBody] = useState('');
  const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  const load = useCallback(() => {
    void loadCommunity(uuid).then(setCommunity);
  }, [uuid]);

  useFocusEffect(load);

  const when = (iso: string, minutes: number) => t('communities.sessions.when', { date: new Date(iso).toLocaleString(i18n.language, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }), minutes });
  const price = (cents: number) => (cents === 0 ? t('communities.sessions.free') : new Intl.NumberFormat(i18n.language, { style: 'currency', currency: 'USD' }).format(cents / 100));
  const ce = (s: CommunitySession) => (s.ce_course === null ? t('communities.sessions.ceNone') : t('communities.sessions.ceCourse', { course: s.ce_course }));
  const say = (work: Promise<unknown>, text: string) =>
    work
      .then(() => setNotice({ tone: 'success', text }))
      .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
      .finally(load);

  const act = (s: CommunitySession) => {
    if (s.mine === 'going') {
      return s.join_url ? () => void Linking.openURL(s.join_url ?? '') : () => void say(cancelRsvp(s.id), t('communities.sessions.cancelledRsvp'));
    }

    return () =>
      void rsvp(s.id)
        .then((r) => (r.checkout_url ? Linking.openURL(r.checkout_url) : setNotice({ tone: 'success', text: t('communities.sessions.rsvped') })))
        .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
        .finally(load);
  };

  return (
    <Screen>
      <TitleBar title={community?.name ?? t('communities.mineTitle')} onBack={() => router.back()} />
      {community ? <Text tone="muted">{`${t('communities.ledBy', { name: community.owner })} · ${t('communities.members', { count: community.members })}`}</Text> : null}
      {community?.about ? <Text>{community.about}</Text> : null}
      {notice ? <Callout tone={notice.tone} title={notice.text} /> : null}

      <SectionTitle>{t('communities.sessions.title')}</SectionTitle>
      {community && community.sessions.length === 0 ? <EmptyState icon={<Icon icon={CalendarDays} color="action" size={32} />} title={t('communities.sessions.empty')} description={t('communities.mineEmptyHint')} /> : null}
      {community?.sessions.map((s) => (
        <ListRow
          key={s.id}
          icon={CalendarDays}
          tone={s.mine === 'going' ? 'success' : 'info'}
          title={s.title}
          subtitle={[
            when(s.starts_at, s.minutes),
            price(s.price_cents),
            ce(s),
            s.mine === 'going'
              ? s.join_url
                ? t('communities.sessions.join')
                : t('communities.sessions.going')
              : s.mine === 'held'
                ? t('communities.sessions.held')
                : s.price_cents === 0
                  ? t('communities.sessions.rsvp')
                  : t('communities.sessions.rsvpPaid', { price: price(s.price_cents) }),
          ].join(' · ')}
          onPress={s.status === 'scheduled' && !community.is_owner ? act(s) : undefined}
        />
      ))}

      <SectionTitle>{t('communities.posts.title')}</SectionTitle>
      <TextField label={t('communities.posts.label')} hint={t('communities.posts.hint')} value={body} onChangeText={setBody} multiline />
      <Button
        label={t('communities.posts.post')}
        disabled={body.trim().length < 2}
        onPress={() =>
          void postTo(uuid, body)
            .then(() => {
              setBody('');
              setNotice({ tone: 'success', text: t('communities.posts.posted') });
            })
            .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
            .finally(load)
        }
      />
      {community && community.posts.length === 0 ? <Text tone="muted">{t('communities.posts.emptyHint')}</Text> : null}
      {community?.posts.map((p) => (
        <ListRow key={p.id} icon={MessageSquare} tone={p.pinned ? 'warning' : 'info'} title={p.author} subtitle={p.pinned ? `${t('communities.posts.pinned')} · ${p.body}` : p.body} />
      ))}

      {community && community.resources.length > 0 ? <SectionTitle>{t('communities.resources.title')}</SectionTitle> : null}
      {community?.resources.map((r) => (
        <ListRow key={r.id} icon={MessageSquare} tone="info" title={r.title} subtitle={r.url} onPress={() => void Linking.openURL(r.url)} />
      ))}
    </Screen>
  );
}
