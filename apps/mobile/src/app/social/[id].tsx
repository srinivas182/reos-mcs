import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { ExternalLink, Pencil, Repeat2, Trash2 } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { Linking } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { api } from '@/lib/api';
import { type Channel, METRICS, removePost, repost, restorePost, type SocialPost, STATUS_TONE } from '@/lib/social';
import { Button, Card, Chip, ListRow, Screen, SectionTitle, Text, TitleBar, useUndoToast } from '@/ui';

/**
 * A post's details (B11; screen 169 → a post): the text with its
 * attribution, each channel's status and results, and Edit, Remove (with
 * Undo) or Post again.
 */
export default function PostScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [post, setPost] = useState<SocialPost | null>(null);

  useFocusEffect(
    useCallback(() => {
      void api()
        .GET('/social/posts/{post}', { params: { path: { post: id } } })
        .then(({ data }) => setPost(data?.data ?? null))
        .catch(() => undefined);
    }, [id]),
  );

  if (post === null) return <Screen>{null}</Screen>;

  const editable = post.status === 'draft' || post.status === 'scheduled';

  return (
    <Screen>
      <TitleBar title={t('social.posts.title')} onBack={() => router.back()} />
      <Card style={styles.card}>
        <Chip label={t(`social.status.${post.status}`)} tone={STATUS_TONE[post.status] ?? 'info'} />
        <Text>{post.body}</Text>
        <Text tone="muted">{post.attribution}</Text>
        {post.scheduled_at && post.status === 'scheduled' ? (
          <Text>{t('social.compose.scheduled', { date: new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(post.scheduled_at)) })}</Text>
        ) : null}
      </Card>
      <SectionTitle>{t('social.compose.chooseAccounts')}</SectionTitle>
      {post.targets.map((target) => (
        <ListRow
          key={target.id}
          icon={ExternalLink}
          tone={STATUS_TONE[target.status] ?? 'info'}
          title={`${t(`social.channels.${target.channel as Channel}`)} · ${target.name}`}
          subtitle={[
            t(`social.status.${target.status}`),
            target.status === 'failed' && target.error === 'reconnect' ? t('social.errors.reconnect') : null,
            ...METRICS.filter((m) => target.insights[m] !== undefined).map((m) => `${t(`social.insights.${m}`)} ${target.insights[m]}`),
          ]
            .filter(Boolean)
            .join(' · ')}
          onPress={target.url ? () => void Linking.openURL(target.url ?? '') : undefined}
        />
      ))}
      {editable ? (
        <>
          <Button icon={Pencil} label={t('social.compose.edit')} onPress={() => router.push({ pathname: '/social/new', params: { post: post.id } })} fullWidth />
          <Button
            variant="secondary"
            icon={Trash2}
            label={t('social.compose.removeMedia')}
            onPress={() =>
              void removePost(post.id).then(() => {
                toast({ message: t('social.compose.removed'), onUndo: () => void restorePost(post.id) });
                router.back();
              })
            }
            fullWidth
          />
        </>
      ) : (
        <Button
          icon={Repeat2}
          label={t('social.compose.repost')}
          onPress={() => void repost(post.id).then((copy) => router.replace({ pathname: '/social/new', params: { post: copy.id } }))}
          fullWidth
        />
      )}
    </Screen>
  );
}

const styles = StyleSheet.create((theme) => ({
  card: { gap: theme.space.sm },
}));
