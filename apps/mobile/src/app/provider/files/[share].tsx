import { router, useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadFile, messageAgent, tickItem, type FileDetail } from '@/lib/files';
import { Button, Callout, Screen, SectionTitle, SwitchRow, Text, TextField, TitleBar } from '@/ui';

/**
 * One file on the phone (B17.1, provider screen 15): only the parts the
 * agent shared, a checklist the agent sees, and a drafted message.
 */
export default function FileScreen() {
  const { t, i18n } = useTranslation();
  const { share } = useLocalSearchParams<{ share: string }>();
  const [file, setFile] = useState<FileDetail | null | undefined>(undefined);
  const [body, setBody] = useState('');
  const [notice, setNotice] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  const load = useCallback(() => {
    void loadFile(share).then((f) => {
      setFile(f);
      setBody((now) => (now === '' && f ? f.draft : now));
    });
  }, [share]);

  useFocusEffect(load);

  const day = (d: string) => new Date(`${d}T12:00:00`).toLocaleDateString(i18n.language, { month: 'short', day: 'numeric' });
  const tick = (item: string, done: boolean) =>
    tickItem(share, item, done)
      .then(() => setNotice({ tone: 'success', text: t('coordinators.ticked', { agent: file?.agent ?? '' }) }))
      .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
      .finally(load);

  if (file === null) {
    return (
      <Screen>
        <TitleBar title={t('coordinators.title')} onBack={() => router.back()} />
        <Callout tone="info" title={t('coordinators.share.ended.closed')} />
      </Screen>
    );
  }

  const notShared = <Text tone="muted">{t('coordinators.notShared', { agent: file?.agent ?? '' })}</Text>;

  return (
    <Screen>
      <TitleBar title={file?.property ?? t('coordinators.title')} onBack={() => router.back()} />
      {file ? <Text tone="muted">{t('coordinators.sharedBy', { agent: file.agent, date: day(file.shared_on) })}</Text> : null}
      {notice ? <Callout tone={notice.tone} title={notice.text} /> : null}

      <SectionTitle>{t('coordinators.dates')}</SectionTitle>
      {file?.dates === null ? notShared : file?.dates.map((d) => <SwitchRow key={d.id} label={t('coordinators.tick', { name: d.label })} description={day(d.due_on)} value={d.done} onValueChange={(on) => void tick(d.id, on)} />)}

      <SectionTitle>{t('coordinators.documents')}</SectionTitle>
      {file?.documents === null
        ? notShared
        : file?.documents.map((d) => (
            <SwitchRow
              key={d.name}
              label={t('coordinators.tick', { name: d.name })}
              description={t(`deals.fileStatus.${d.status === 'complete' ? 'complete' : d.status === 'missing' ? 'missing' : 'pending'}`)}
              value={d.done}
              disabled={d.status === 'complete'}
              onValueChange={(on) => void tick(`doc:${d.name}`, on)}
            />
          ))}

      <SectionTitle>{t('coordinators.parties')}</SectionTitle>
      {file?.parties === null ? notShared : file?.parties.map((p) => <Text key={`${p.name}-${p.role}`}>{`${p.name} · ${p.role}`}</Text>)}

      {file ? (
        <>
          <SectionTitle>{t('coordinators.message', { agent: file.agent })}</SectionTitle>
          {file.switches.messages ? (
            <>
              <TextField label={t('coordinators.message', { agent: file.agent })} hint={t('coordinators.messageHint', { agent: file.agent })} value={body} onChangeText={setBody} multiline />
              <Button
                label={t('coordinators.send')}
                disabled={body.trim().length < 2}
                onPress={() =>
                  void messageAgent(share, body)
                    .then((c) => {
                      setNotice({ tone: 'success', text: t('coordinators.sent', { agent: file.agent }) });
                      router.push({ pathname: '/messages/[id]', params: { id: c } });
                    })
                    .catch((e: Error) => setNotice({ tone: 'danger', text: e.message }))
                }
              />
            </>
          ) : (
            <Text tone="muted">{t('coordinators.messagesOff', { agent: file.agent })}</Text>
          )}
        </>
      ) : null}
    </Screen>
  );
}
