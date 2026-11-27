/**
 * Social posting on the phone (B11; agent screens 108, 169, 170): social
 * accounts and where they post, the posts with their results, checks,
 * publish now or schedule, drafts, Undo and Repost.
 */
import type { paths } from '@reos/api-client';
import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';

import { api } from '@/lib/api';
import type { Tone } from '@/ui/tones';

type Got<P extends keyof paths> = paths[P] extends { get: { responses: { 200: { content: { 'application/json': infer B } } } } } ? B : never;

export type SocialAccount = Got<'/social/accounts'>['data'][number];
export type PostList = Got<'/social/posts'>['data'];
export type SocialPost = PostList['posted'][number];
export type CheckResult = paths['/social/posts/check']['post']['responses'][200]['content']['application/json']['data'];
export type Pick = { connection: number; channel: string; id: string };
export type PostBody = { body: string; media: string[]; link: string | null; targets: Pick[]; when: 'now' | 'later' | 'draft'; at: string | null; listing: string | null; ask_broker?: string | null };
export type Channel = 'facebook' | 'instagram' | 'linkedin' | 'google_business' | 'youtube' | 'tiktok';
export type Metric = 'reach' | 'views' | 'likes' | 'comments' | 'clicks' | 'shares';
export const STATUS_TONE: Record<string, Tone> = { draft: 'neutral', approval: 'warning', scheduled: 'info', publishing: 'info', queued: 'neutral', published: 'success', partly: 'warning', failed: 'danger' };
export const METRICS: Metric[] = ['reach', 'views', 'likes', 'comments', 'clicks', 'shares'];

const fail = (error: unknown): never => {
  const e = (error as { errors?: Record<string, string[]>; message?: string } | undefined) ?? {};
  throw new Error(Object.values(e.errors ?? {})[0]?.[0] ?? e.message ?? 'error');
};

export async function checkPost(body: PostBody): Promise<CheckResult> {
  const { data, error } = await api().POST('/social/posts/check', { body: { ...body, when: 'draft' } });

  return data ? data.data : fail(error);
}

export async function savePost(body: PostBody, id?: string): Promise<SocialPost> {
  const { data, error } = id ? await api().PUT('/social/posts/{post}', { params: { path: { post: id } }, body }) : await api().POST('/social/posts', { body });

  return data ? data.data : fail(error);
}

export async function removePost(id: string): Promise<void> {
  const { error } = await api().DELETE('/social/posts/{post}', { params: { path: { post: id } } });
  if (error) fail(error);
}

export async function restorePost(id: string): Promise<void> {
  const { error } = await api().POST('/social/posts/{post}/restore', { params: { path: { post: id } } });
  if (error) fail(error);
}

export async function repost(id: string): Promise<SocialPost> {
  const { data, error } = await api().POST('/social/posts/{post}/repost', { params: { path: { post: id } } });

  return data ? data.data : fail(error);
}

export async function refreshTargets(connection: number): Promise<SocialAccount['targets']> {
  const { data, error } = await api().POST('/social/accounts/{connection}/refresh', { params: { path: { connection } } });

  return data ? data.data : fail(error);
}

/** A photo or video from the phone. */
export async function uploadMedia(file: { uri: string; name: string; type: string }): Promise<{ path: string; url: string; kind: string }> {
  const form = new FormData();
  // React Native's FormData takes { uri, name, type } for a file.
  form.append('file', file as unknown as Blob);
  const { data, error } = await api().POST('/social/media', { body: { file: '' }, bodySerializer: () => form });

  return data ? data.data : fail(error);
}

export function useSocialAccounts() {
  const [accounts, setAccounts] = useState<SocialAccount[] | null>(null);
  const reload = useCallback(async () => {
    const { data } = await api().GET('/social/accounts');
    setAccounts(data?.data ?? []);
  }, []);

  useFocusEffect(
    useCallback(() => {
      void reload().catch(() => setAccounts([]));
    }, [reload]),
  );

  return { accounts, setAccounts, reload };
}

export function usePosts() {
  const [posts, setPosts] = useState<PostList | null>(null);
  const reload = useCallback(async () => {
    const { data } = await api().GET('/social/posts');
    setPosts(data?.data ?? null);
  }, []);

  useFocusEffect(
    useCallback(() => {
      void reload().catch(() => undefined);
    }, [reload]),
  );

  return { posts, reload };
}
