import { Image } from 'expo-image';
import { router, useFocusEffect } from 'expo-router';
import { BadgeCheck, CalendarDays, ChartColumn, House, IdCard, SearchX, Share2 } from 'lucide-react-native';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ActivityIndicator, Share, View } from 'react-native';
import { StyleSheet, useUnistyles } from 'react-native-unistyles';

import {
  confirmMember,
  declineMember,
  loadListings,
  loadPage,
  priceText,
  savePage,
  unlinkMember,
  type Listing,
  type ListingsOverview,
  type Member,
  type PageSettings,
} from '@/lib/listings';
import { Button, Callout, Card, Chip, EmptyState, Icon, ListRow, Screen, SectionTitle, SwitchRow, Text, TitleBar, useUndoToast } from '@/ui';
import type { Tone } from '@/ui';

const TONES: Record<string, Tone> = { coming_soon: 'info', active: 'success', under_contract: 'warning', pending: 'warning', closed: 'neutral' };

/**
 * My listings (B10.1, Agent App Deals): MLS listings where the agent is the
 * listing or co-listing agent, on the market first. "Is this you?" for the
 * member record found by their license number; Simulator data says
 * "Sample data".
 */
export default function ListingsScreen() {
  const { t, i18n } = useTranslation();
  const { theme } = useUnistyles();
  const toast = useUndoToast();
  const [overview, setOverview] = useState<ListingsOverview | null | undefined>(undefined);
  const [problem, setProblem] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  useFocusEffect(
    useCallback(() => {
      void loadListings()
        .catch(() => null)
        .then(setOverview);
    }, []),
  );

  const run = async (work: () => Promise<ListingsOverview>) => {
    setBusy(true);
    try {
      setOverview(await work());
      setProblem(null);

      return true;
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));

      return false;
    } finally {
      setBusy(false);
    }
  };

  const unlink = async (member: Member) => {
    if (await run(() => unlinkMember(member))) {
      toast({ message: t('listings.linked.unlinked', { mls: member.short_name }), onUndo: () => void run(() => confirmMember(member)) });
    }
  };

  return (
    <Screen>
      <TitleBar title={t('listings.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('listings.description')}</Text>
      {overview === undefined ? (
        <ActivityIndicator color={theme.colors.action} />
      ) : overview === null ? (
        <Callout tone="warning" title={t('common.tryAgain')} live />
      ) : (
        <>
          {overview.sample ? <Callout tone="info" title={t('listings.sample')} body={t('listings.sampleNote')} /> : null}
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          {overview.candidates.map((member) => (
            <Card key={`${member.mls}-${member.member_key}`} style={styles.gap}>
              <Text variant="title">{t('listings.found.title')}</Text>
              <Text tone="muted">{t('listings.found.description', { mls: member.short_name })}</Text>
              <Text weight="semibold">{member.name}</Text>
              {member.office ? <Text tone="muted">{[member.office, member.office_city].filter(Boolean).join(', ')}</Text> : null}
              <Button label={t('listings.found.confirm')} disabled={busy} onPress={() => void run(() => confirmMember(member))} />
              <Button variant="secondary" label={t('listings.found.decline')} disabled={busy} onPress={() => void run(() => declineMember(member))} />
            </Card>
          ))}
          {!overview.licensed ? (
            <EmptyState
              icon={<Icon icon={IdCard} color="action" size={32} />}
              title={t('listings.noLicence.title')}
              description={t('listings.noLicence.description')}
              action={<Button label={t('listings.noLicence.action')} onPress={() => router.push('/licences')} />}
            />
          ) : null}
          {overview.licensed && overview.linked.length === 0 && overview.candidates.length === 0 ? (
            <EmptyState icon={<Icon icon={SearchX} color="action" size={32} />} title={t('listings.notFound.title')} description={t('listings.notFound.description')} />
          ) : null}
          {overview.linked.length > 0 ? (
            <>
              <SectionTitle>{t('listings.linked.title')}</SectionTitle>
              {overview.linked.map((member) => (
                <ListRow
                  key={`${member.mls}-${member.member_key}`}
                  icon={BadgeCheck}
                  tone="success"
                  title={`${member.short_name} · ${member.name}`}
                  subtitle={member.office ?? undefined}
                  trailing={
                    <Button
                      variant="ghost"
                      label={t('listings.linked.unlink')}
                      accessibilityLabel={t('listings.linked.unlinkLabel', { mls: member.short_name })}
                      disabled={busy}
                      onPress={() => void unlink(member)}
                    />
                  }
                />
              ))}
            </>
          ) : null}
          {overview.linked.length > 0 && overview.listings.length === 0 ? (
            <EmptyState icon={<Icon icon={House} color="action" size={32} />} title={t('listings.empty.title')} description={t('listings.empty.description')} />
          ) : null}
          {overview.listings.map((listing) => (
            <ListingCard key={listing.uuid} listing={listing} language={i18n.language} />
          ))}
          {overview.linked.length > 0 ? <PageCard /> : null}
        </>
      )}
    </Screen>
  );
}

/** Your public page (B10.2b): publish it and share its link. */
function PageCard() {
  const { t } = useTranslation();
  const [page, setPage] = useState<PageSettings | null>(null);

  useEffect(() => {
    void loadPage().then(setPage);
  }, []);

  if (page === null) return null;

  return (
    <Card style={styles.gap}>
      <Text variant="title">{t('listings.page.title')}</Text>
      <Text tone="muted">{t('listings.page.description')}</Text>
      <SwitchRow label={t('listings.page.publish')} value={page.published} onValueChange={(on) => void savePage(on, page.bio).then(setPage)} />
      {/* Brokerage rules (B13.2): the page waits for the broker, or came back with a note. */}
      {page.waiting ? <Callout tone="info" title={t('compliance.pageWaiting')} /> : null}
      {page.note ? <Callout tone="warning" title={t('compliance.pageReturned', { note: page.note })} /> : null}
      {page.published ? (
        <>
          <Text tone="muted">{page.url}</Text>
          <Button variant="secondary" icon={Share2} label={t('listings.post.share')} onPress={() => void Share.share({ message: page.url })} />
          {/* The digital card (B14.1): contact details, QR code and sharing. */}
          <Button variant="secondary" icon={IdCard} label={t('listings.page.cardTitle')} onPress={() => router.push('/card')} />
        </>
      ) : null}
    </Card>
  );
}

function ListingCard({ listing, language }: { listing: Listing; language: string }) {
  const { t } = useTranslation();
  const facts = [
    listing.beds === null ? null : t('listings.beds', { count: listing.beds }),
    listing.baths === null ? null : t('listings.baths', { count: listing.baths }),
    listing.area === null ? null : t('listings.area', { area: new Intl.NumberFormat(language).format(listing.area) }),
  ].filter((fact): fact is string => fact !== null);
  const when = listing.open_house
    ? new Intl.DateTimeFormat(language, { weekday: 'short', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }).format(new Date(listing.open_house.starts_at))
    : null;

  return (
    <Card style={styles.gap}>
      {listing.photo ? (
        <Image
          source={{ uri: listing.photo.url }}
          style={styles.photo}
          contentFit="cover"
          accessibilityLabel={listing.photo.caption ?? t('listings.photo', { address: listing.address })}
          accessibilityIgnoresInvertColors
        />
      ) : null}
      <View style={styles.row}>
        <Chip label={t(`listings.status.${listing.status}`, { defaultValue: listing.status })} tone={TONES[listing.status] ?? 'neutral'} />
        <Text tone="muted">{t(`listings.role.${listing.role}`)}</Text>
      </View>
      <Text variant="title">{priceText(listing, language, (price) => t('listings.soldFor', { price }))}</Text>
      <Text weight="semibold">{listing.address}</Text>
      {facts.length > 0 ? <Text tone="muted">{facts.join(' · ')}</Text> : null}
      <Text variant="caption" tone="muted">
        {[
          listing.listing_id ? t('listings.mlsNumber', { mls: listing.short_name, id: listing.listing_id }) : null,
          listing.days_on_market === null ? null : t('listings.daysOnMarket', { count: listing.days_on_market }),
        ]
          .filter(Boolean)
          .join(' · ')}
      </Text>
      <Text variant="caption" tone="muted">
        {listing.attribution}
      </Text>
      {listing.open_house && when ? (
        <View style={styles.row}>
          <Icon icon={CalendarDays} color="action" size={20} />
          <Text>{t(listing.open_house.kind === 'broker' ? 'listings.brokerOpen' : 'listings.openHouse', { time: when })}</Text>
        </View>
      ) : null}
      {listing.can_post ? (
        <Button
          variant="secondary"
          icon={Share2}
          label={t('listings.post.create')}
          accessibilityLabel={t('listings.post.createFor', { address: listing.address })}
          onPress={() => router.push({ pathname: '/deals/listings/[uuid]/post', params: { uuid: listing.uuid } })}
        />
      ) : null}
      <Button
        variant="secondary"
        icon={ChartColumn}
        label={t('listings.cma.open')}
        accessibilityLabel={t('listings.cma.openFor', { address: listing.address })}
        onPress={() => router.push({ pathname: '/deals/listings/[uuid]/cma', params: { uuid: listing.uuid } })}
      />
    </Card>
  );
}

const styles = StyleSheet.create((theme) => ({
  gap: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: theme.space.sm },
  photo: { width: '100%', aspectRatio: 4 / 3, borderRadius: theme.m.radius.card },
}));
