import { router, useFocusEffect } from 'expo-router';
import { MessageSquare, Trophy, UserRound, Users, Video } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { slotText } from '@/lib/bookings';
import { joinChallenge, joinPod, leavePod, loadChallenges, loadPod, rejoinPod, setPodGoal, setPodShare, SHARE_METRICS, type Challenge, type PodView, type ShareMetric } from '@/lib/pods';
import { Button, Callout, Card, EmptyState, Icon, ListRow, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar, useUndoToast } from '@/ui';

/**
 * Your pod (agent screen 131; A13.2): five agents with the same goal at
 * your brokerage — this week's goals and the numbers each chose to share,
 * the 15-minute call, pod messages — and the brokerage's opt-in challenges.
 */
export default function PodScreen() {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [pod, setPod] = useState<PodView | null>(null);
  const [challenges, setChallenges] = useState<Challenge[]>([]);
  const [goal, setGoal] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const show = (p: PodView) => {
    setPod(p);
    setGoal(p.pod?.members.find((m) => m.me)?.goal ?? '');
  };

  useFocusEffect(
    useCallback(() => {
      void loadPod().then((p) => p && show(p));
      void loadChallenges().then(setChallenges);
    }, []),
  );

  const run = async (work: () => Promise<void>) => {
    setBusy(true);
    setError(null);
    try {
      await work();
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setBusy(false);
    }
  };

  const me = pod?.pod?.members.find((m) => m.me);
  const shares = (me?.shares ?? []) as ShareMetric[];

  return (
    <Screen>
      <TitleBar title={t('pods.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('pods.purpose')}</Text>
      {error ? <Callout tone="danger" title={error} /> : null}

      {pod && pod.status !== 'member' ? (
        <EmptyState
          icon={<Icon icon={Users} color="action" size={32} />}
          title={pod.status === 'waiting' ? t('pods.waiting') : t('pods.none')}
          description={pod.status === 'waiting' ? t('pods.waitingHint') : t('pods.noneHint')}
          action={
            pod.status === 'waiting' ? (
              <Button label={t('pods.cancel')} variant="secondary" loading={busy} onPress={() => void run(async () => show((await leavePod()).pod))} />
            ) : (
              <Button label={t('pods.join')} loading={busy} onPress={() => void run(async () => show(await joinPod()))} />
            )
          }
        />
      ) : null}

      {pod?.pod ? (
        <>
          <Card>
            <Text weight="semibold">{pod.pod.name}</Text>
            {pod.pod.call.next ? <Text tone="muted">{t('pods.callNext', { time: slotText(pod.pod.call.next, i18n.language) })}</Text> : null}
            <Text tone="muted">{t('pods.private')}</Text>
          </Card>
          {pod.pod.call.session ? <ListRow icon={Video} tone="info" title={t('pods.joinCall')} onPress={() => router.push({ pathname: '/sessions/[id]', params: { id: pod.pod?.call.session ?? '' } })} /> : null}
          {pod.pod.conversation ? <ListRow icon={MessageSquare} tone="info" title={t('pods.message')} onPress={() => router.push({ pathname: '/messages/[id]', params: { id: pod.pod?.conversation ?? '' } })} /> : null}

          <SectionTitle>{t('pods.week', { date: new Intl.DateTimeFormat(i18n.language, { month: 'long', day: 'numeric', timeZone: 'UTC' }).format(new Date(`${pod.pod.week_of}T12:00:00Z`)) })}</SectionTitle>
          {pod.pod.members.map((m) => (
            <ListRow
              key={m.id}
              icon={UserRound}
              tone={m.me ? 'info' : undefined}
              title={m.me ? `${m.name} (${t('pods.you')})` : m.name}
              subtitle={[m.goal ?? t('pods.noGoal'), ...m.metrics.map((x) => `${t(`mentoring.workspace.metrics.${x.key as ShareMetric}`)}: ${t('mentoring.workspace.metricValue', { done: x.done, target: x.target })}`)].join(' · ')}
            />
          ))}

          <TextField label={t('pods.yourGoal')} value={goal} maxLength={200} placeholder={t('pods.goalPlaceholder')} onChangeText={setGoal} />
          <Button label={t('pods.saveGoal')} loading={busy} disabled={goal.trim().length < 2} onPress={() => void run(async () => show(await setPodGoal(goal.trim())))} />

          <SectionTitle>{t('pods.shares')}</SectionTitle>
          <Text tone="muted">{t('pods.sharesHint')}</Text>
          {SHARE_METRICS.map((k) => (
            <SwitchRow
              key={k}
              label={t(`mentoring.workspace.metrics.${k}`)}
              value={shares.includes(k)}
              disabled={busy}
              onValueChange={(on) => void run(async () => show(await setPodShare(on ? [...shares, k] : shares.filter((x) => x !== k))))}
            />
          ))}

          <Button
            label={t('pods.leave')}
            variant="ghost"
            loading={busy}
            onPress={() =>
              void run(async () => {
                const { pod: next, left } = await leavePod();
                show(next);
                if (left) toast({ message: t('pods.left'), onUndo: () => void rejoinPod(left).then(show) });
              })
            }
          />
        </>
      ) : null}

      <SectionTitle>{t('pods.challenges.title')}</SectionTitle>
      <Text tone="muted">{t('pods.challenges.purpose')}</Text>
      {challenges.length === 0 ? <EmptyState icon={<Icon icon={Trophy} color="action" size={32} />} title={t('pods.challenges.none')} description={t('pods.challenges.noneHint')} /> : null}
      {challenges.map((c) => (
        <Card key={c.id}>
          <Text weight="semibold">{c.title}</Text>
          <Text tone="muted">{`${t(`pods.challenges.metrics.${c.metric}`)} · ${t('pods.challenges.entrants', { count: c.entrants })}`}</Text>
          {c.total.slice(0, 3).map((r, i) => (
            <Text key={`t${r.id}`}>{`${t('pods.challenges.total')} ${i + 1}. ${r.name} · ${r.value}`}</Text>
          ))}
          {c.improved.slice(0, 3).map((r, i) => (
            <Text key={`i${r.id}`}>{`${t('pods.challenges.improved')} ${i + 1}. ${r.name} · ${r.improved > 0 ? '+' : ''}${r.improved}${r.personal_best ? ` · ${t('pods.challenges.personalBest')}` : ''}`}</Text>
          ))}
          {c.open ? (
            <Button
              label={c.joined ? t('pods.challenges.leave') : t('pods.challenges.join')}
              variant={c.joined ? 'secondary' : 'primary'}
              loading={busy}
              onPress={() =>
                void run(async () => {
                  setChallenges(await joinChallenge(c.id, !c.joined));
                  if (c.joined) toast({ message: t('pods.challenges.leftOne'), onUndo: () => void joinChallenge(c.id).then(setChallenges) });
                })
              }
            />
          ) : (
            <Text tone="muted">{t('pods.challenges.ended')}</Text>
          )}
        </Card>
      ))}
    </Screen>
  );
}
