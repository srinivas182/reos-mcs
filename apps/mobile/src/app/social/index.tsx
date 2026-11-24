import { router } from 'expo-router';
import { Megaphone, Plug, Plus } from 'lucide-react-native';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type Channel, STATUS_TONE, usePosts, type SocialPost } from '@/lib/social';
import { Card, Chip, EmptyState, Icon, IconButton, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * My posts and results (B11; agent screen 169, Me → My posts): this
 * month's posts, reach, engagement and clicks; Scheduled, Posted and
 * Drafts, each with its channels and results; + to create a post.
 */
export default function PostsScreen() {
  const { t, i18n } = useTranslation();
  const { posts } = usePosts();

  if (posts === null) return <Screen>{null}</Screen>;

  const sections = [
    ['scheduled', posts.scheduled],
    ['posted', posts.posted],
    ['drafts', posts.drafts],
  ] as const;
  const number = (n: number) => new Intl.NumberFormat(i18n.language).format(n);
  const row = (post: SocialPost) => (
    <ListRow
      key={post.id}
      icon={Megaphone}
      tone={STATUS_TONE[post.status] ?? 'info'}
      title={post.body.length > 80 ? `${post.body.slice(0, 80)}…` : post.body}
      subtitle={[post.targets.map((x) => t(`social.channels.${x.channel as Channel}`)).join(', '), post.totals.reach ? `${t('social.insights.reach')} ${number(post.totals.reach)}` : null].filter(Boolean).join(' · ')}
      trailing={<Chip label={t(`social.status.${post.status}`)} tone={STATUS_TONE[post.status] ?? 'info'} />}
      onPress={() => router.push({ pathname: '/social/[id]', params: { id: post.id } })}
    />
  );

  return (
    <Screen>
      <TitleBar title={t('social.posts.title')} onBack={() => router.back()} action={<IconButton icon={Plus} primary label={t('social.posts.new')} onPress={() => router.push('/social/new')} />} />
      <View style={styles.stats}>
        {(['posts_this_month', 'reach', 'engagement', 'clicks'] as const).map((key) => (
          <Card key={key} style={styles.stat}>
            <Text tone="muted">{t(`social.posts.stats.${key}`)}</Text>
            <Text variant="title">{number(posts.stats[key])}</Text>
          </Card>
        ))}
      </View>
      <Text tone="muted">{t('social.posts.resultsNote')}</Text>
      <ListRow icon={Plug} tone="info" title={t('social.accounts.title')} subtitle={t('social.accounts.description')} onPress={() => router.push('/social/accounts')} />
      {posts.scheduled.length + posts.posted.length + posts.drafts.length === 0 ? (
        <EmptyState icon={<Icon icon={Megaphone} color="action" size={32} />} title={t('social.posts.emptyTitle')} description={t('social.posts.emptyBody')} />
      ) : null}
      {sections.map(([key, list]) =>
        list.length === 0 ? null : (
          <View key={key} style={styles.section}>
            <SectionTitle>{t(`social.posts.${key}`, { count: list.length })}</SectionTitle>
            {list.map(row)}
          </View>
        ),
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  stats: { flexDirection: 'row', flexWrap: 'wrap', gap: theme.space.sm },
  stat: { flexGrow: 1, flexBasis: 140, gap: theme.space.xs },
  section: { gap: theme.space.sm },
}));
