import { router, useFocusEffect } from 'expo-router';
import { Heart, PartyPopper } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadPasses, type PassFeed, sendCongrats } from '@/lib/licence';
import { Button, Callout, Card, EmptyState, Icon, Screen, Text, TextField, TitleBar } from '@/ui';

type Pass = PassFeed['passes'][number];

function PassCard({ pass, onCongrats }: { pass: Pass; onCongrats: (message: string) => void }) {
  const { t } = useTranslation();
  const [writing, setWriting] = useState(false);
  const [message, setMessage] = useState(t('licence.news.passes.defaultMessage', { name: pass.first_name }));
  const name = pass.mine ? t('licence.news.passes.you') : pass.first_name;

  return (
    <Card>
      <Text weight="semibold">{pass.city ? t('licence.news.passes.lineCity', { name, city: pass.city, state: pass.state }) : t('licence.news.passes.line', { name, state: pass.state })}</Text>
      {pass.tip ? <Text>{`“${pass.tip}”`}</Text> : null}
      <Text variant="caption" tone="muted">
        {t('licence.news.passes.congrats', { count: pass.congrats })}
      </Text>
      {pass.mine ? null : pass.sent ? (
        <Text tone="success">{t('licence.news.passes.sent')}</Text>
      ) : writing ? (
        <>
          <TextField label={t('licence.news.passes.messageLabel', { name: pass.first_name })} value={message} onChangeText={setMessage} maxLength={140} />
          <Button
            icon={Heart}
            label={t('licence.news.passes.send')}
            disabled={message.trim().length < 2}
            onPress={() => {
              onCongrats(message.trim());
              setWriting(false);
            }}
          />
        </>
      ) : (
        <Button variant="secondary" icon={Heart} label={t('licence.news.passes.send')} onPress={() => setWriting(true)} />
      )}
    </Card>
  );
}

/**
 * Passes on the phone (f06, A10.3b): wins from candidates in the state and
 * the study group who chose to share, the week's count, Send congrats, a
 * simulation and the candidate's own readiness.
 */
export default function PassesScreen() {
  const { t } = useTranslation();
  const [feed, setFeed] = useState<PassFeed | null>(null);
  const [problem, setProblem] = useState<string | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadPasses().then(setFeed);
    }, []),
  );

  const congrats = async (pass: number, message: string) => {
    try {
      setFeed(await sendCongrats(pass, message));
      setProblem(null);
    } catch (e) {
      setProblem(e instanceof Error ? e.message : String(e));
    }
  };

  return (
    <Screen>
      <TitleBar title={t('licence.news.passes.title')} onBack={() => router.back()} />
      {feed ? (
        <>
          <Text tone="muted">{t('licence.news.passes.purpose', { state: feed.state })}</Text>
          <Text weight="semibold">{t('licence.news.passes.week', { count: feed.week, state: feed.state })}</Text>
          {feed.readiness !== null ? <Text tone="muted">{t('licence.news.passes.readiness', { percent: feed.readiness })}</Text> : null}
          <Button variant="secondary" label={t('licence.news.passes.simulation')} onPress={() => router.push('/practice')} />
          {problem ? <Callout tone="danger" title={problem} live /> : null}
          {feed.passes.length === 0 ? (
            <EmptyState icon={<Icon icon={PartyPopper} color="action" size={32} />} title={t('licence.news.passes.empty')} description={t('licence.news.passes.emptyBody')} />
          ) : null}
          {feed.passes.map((pass) => (
            <PassCard key={pass.id} pass={pass} onCongrats={(message) => void congrats(pass.id, message)} />
          ))}
        </>
      ) : null}
    </Screen>
  );
}
