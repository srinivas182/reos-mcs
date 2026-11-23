/**
 * The class tools on the phone (A10.2b; candidate L12, instructor i12):
 * an attendance check to answer, Raise hand, questions with likes, the
 * poll and chat; for the host also raised hands (Bring on), launching a
 * poll and attendance checks with their answers.
 */
import { Check, Hand, ThumbsUp, UserCheck } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { type ClassActions, type ClassLive, useClassLive } from '@/lib/classes';
import { Button, Callout, Card, PillGroup, Text, TextField } from '@/ui';

type Tab = 'questions' | 'poll' | 'chat' | 'hands' | 'attendance';

const time = (iso: string, language: string) => new Date(iso).toLocaleTimeString(language, { hour: 'numeric', minute: '2-digit' });

export function ClassPanel({ live, actions, problem }: { live: ClassLive; actions: ClassActions; problem?: string | null }) {
  const { t, i18n } = useTranslation();
  const host = live.me.role === 'host';
  const [tab, setTab] = useState<Tab>('questions');
  const [question, setQuestion] = useState('');
  const [message, setMessage] = useState('');
  const [pollQuestion, setPollQuestion] = useState('');
  const [options, setOptions] = useState(['', '']);
  const poll = live.polls[0] ?? null;
  const filled = options.map((o) => o.trim()).filter(Boolean);

  const tabs: { value: Tab; label: string }[] = [
    { value: 'questions', label: t('classes.tabs.questions') },
    { value: 'poll', label: t('classes.tabs.poll') },
    { value: 'chat', label: t('classes.tabs.chat') },
    ...(host
      ? [
          { value: 'hands' as const, label: t('classes.tabs.hands', { number: live.hands.filter((h) => !h.on_stage).length }) },
          { value: 'attendance' as const, label: t('classes.tabs.attendance') },
        ]
      : []),
  ];

  return (
    <View style={styles.panel} accessibilityLabel={t('classes.panel')}>
      <View style={styles.row}>
        <Text tone="muted">{t('classes.viewers', { count: live.viewers })}</Text>
        {!host ? (
          <Button variant={live.me.hand ? 'primary' : 'secondary'} icon={Hand} label={live.me.hand ? t('classes.lowerHand') : t('classes.raiseHand')} onPress={() => void actions.hand(!live.me.hand)} />
        ) : null}
      </View>
      {live.check && !live.check.answered ? (
        <Callout
          tone="warning"
          title={t('classes.check.title')}
          body={t('classes.check.body', { time: time(live.check.closes_at, i18n.language) })}
          action={<Button icon={Check} label={t('classes.check.here')} onPress={() => (live.check ? void actions.answerCheck(live.check.id) : undefined)} />}
          live
        />
      ) : null}
      {live.check?.answered ? <Text tone="success">{t('classes.check.answered')}</Text> : null}
      {live.me.on_stage ? <Text weight="semibold">{t('classes.onStage')}</Text> : null}

      <PillGroup label={t('classes.panel')} value={tab} options={tabs} onChange={setTab} />
      {problem ? <Callout tone="danger" title={problem} live /> : null}

      {tab === 'questions' ? (
        <View style={styles.section}>
          {!host ? (
            <>
              <TextField label={t('classes.ask.label')} value={question} onChangeText={setQuestion} maxLength={500} multiline />
              <Button
                label={t('classes.ask.send')}
                disabled={question.trim().length < 3}
                onPress={() => {
                  void actions.ask(question.trim());
                  setQuestion('');
                }}
              />
            </>
          ) : null}
          {live.questions.length === 0 ? <Text tone="muted">{host ? t('classes.ask.emptyHost') : t('classes.ask.empty')}</Text> : null}
          {live.questions.map((q) => (
            <Card key={q.id}>
              <Text>{q.body}</Text>
              <Text variant="caption" tone="muted">
                {(q.mine ? t('classes.ask.you') : q.by) + (q.answered ? ` · ${t('classes.ask.answered')}` : '') + ` · ${t('classes.ask.likes', { count: q.likes })}`}
              </Text>
              {host ? (
                <Button variant="secondary" label={q.answered ? t('classes.ask.reopen') : t('classes.ask.markAnswered')} onPress={() => void actions.answered(q.id, !q.answered)} />
              ) : !q.mine ? (
                <Button variant={q.liked ? 'primary' : 'secondary'} icon={ThumbsUp} label={t('classes.ask.like', { count: q.likes })} onPress={() => void actions.like(q.id, !q.liked)} />
              ) : null}
            </Card>
          ))}
        </View>
      ) : null}

      {tab === 'poll' ? (
        <View style={styles.section}>
          {host ? (
            <Card>
              <TextField label={t('classes.poll.question')} value={pollQuestion} onChangeText={setPollQuestion} maxLength={200} />
              {options.map((option, i) => (
                <TextField key={i} label={t('classes.poll.option', { number: i + 1 })} value={option} maxLength={80} onChangeText={(text) => setOptions(options.map((o, j) => (j === i ? text : o)))} />
              ))}
              {options.length < 5 ? <Button variant="secondary" label={t('classes.poll.addOption')} onPress={() => setOptions([...options, ''])} /> : null}
              <Button
                label={t('classes.poll.launch')}
                disabled={pollQuestion.trim().length < 3 || filled.length < 2}
                onPress={() => {
                  void actions.poll(pollQuestion.trim(), filled);
                  setPollQuestion('');
                  setOptions(['', '']);
                }}
              />
            </Card>
          ) : null}
          {poll === null ? (
            <Text tone="muted">{t('classes.poll.none')}</Text>
          ) : (
            <Card>
              <Text weight="semibold">{poll.question + (poll.closed ? ` · ${t('classes.poll.closed')}` : '')}</Text>
              {poll.total === null
                ? poll.options.map((option, i) => <Button key={option.label} variant="secondary" label={option.label} onPress={() => void actions.vote(poll.id, i)} />)
                : poll.options.map((option, i) => {
                    const share = (poll.total ?? 0) > 0 ? Math.round(((option.votes ?? 0) / (poll.total ?? 1)) * 100) : 0;

                    return (
                      <Text key={option.label}>
                        {`${option.label}${poll.my_vote === i ? ` · ${t('classes.poll.yours')}` : ''}: ${t('classes.poll.share', { share, votes: option.votes ?? 0 })}`}
                      </Text>
                    );
                  })}
              {host && !poll.closed ? <Button variant="secondary" label={t('classes.poll.close')} onPress={() => void actions.closePoll(poll.id)} /> : null}
            </Card>
          )}
        </View>
      ) : null}

      {tab === 'chat' ? (
        <View style={styles.section}>
          {live.messages.length === 0 ? <Text tone="muted">{t('classes.chat.empty')}</Text> : null}
          {live.messages.map((m) => (
            <Text key={m.id}>
              <Text weight="semibold">{m.mine ? t('classes.ask.you') : m.by}</Text>
              {` ${m.body}`}
            </Text>
          ))}
          <TextField label={t('classes.chat.label')} value={message} onChangeText={setMessage} maxLength={500} />
          <Button
            label={t('classes.chat.send')}
            disabled={message.trim() === ''}
            onPress={() => {
              void actions.chat(message.trim());
              setMessage('');
            }}
          />
        </View>
      ) : null}

      {tab === 'hands' && host ? (
        <View style={styles.section}>
          {live.hands.length === 0 ? <Text tone="muted">{t('classes.hands.none')}</Text> : null}
          {live.hands.map((h) => (
            <View key={h.id} style={styles.row}>
              <Text>{h.name + (h.on_stage ? ` · ${t('classes.hands.speaking')}` : '')}</Text>
              <Button variant={h.on_stage ? 'secondary' : 'primary'} label={h.on_stage ? t('classes.hands.takeOff') : t('classes.hands.bringOn')} onPress={() => void actions.stage(h.id, !h.on_stage)} />
            </View>
          ))}
        </View>
      ) : null}

      {tab === 'attendance' && host ? (
        <View style={styles.section}>
          <Text tone="muted">
            {live.ce ? (live.next_check_at ? t('classes.attendance.next', { time: time(live.next_check_at, i18n.language) }) : t('classes.attendance.noMore')) : t('classes.attendance.notCe')}
          </Text>
          <Button variant="secondary" icon={UserCheck} label={t('classes.attendance.send')} onPress={() => void actions.sendCheck()} />
          {live.checks.map((c) => (
            <Text key={c.id}>{t('classes.attendance.row', { time: time(c.opens_at, i18n.language), answered: c.answered, of: c.of })}</Text>
          ))}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  panel: { gap: theme.space.md },
  section: { gap: theme.space.sm },
  row: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', gap: theme.space.sm, flexWrap: 'wrap' },
}));

/** The panel with its data, for the room. */
export function LiveClassPanel({ sessionId, me }: { sessionId: string; me: number }) {
  const { live, problem, actions } = useClassLive(sessionId, me > 0 ? me : null);

  return live ? <ClassPanel live={live} actions={actions} problem={problem} /> : null;
}
