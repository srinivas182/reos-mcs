import { router, useFocusEffect } from 'expo-router';
import { Inbox, MessageCircleQuestion } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { loadStudentQuestions, type StudentQuestions } from '@/lib/ask';
import { EmptyState, Icon, ListRow, PillGroup, Screen, Text, TitleBar } from '@/ui';

/**
 * Student questions (instructor screen i09, A10.1b): what the course
 * content couldn't answer, waiting or answered; how many Ask answered from
 * the instructor's content. Tap one to answer it.
 */
export default function StudentQuestionsScreen() {
  const { t } = useTranslation();
  const [status, setStatus] = useState<'waiting' | 'answered'>('waiting');
  const [list, setList] = useState<StudentQuestions | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadStudentQuestions(status).then(setList);
    }, [status]),
  );

  return (
    <Screen>
      <TitleBar title={t('ask.instructor.title')} onBack={() => router.back()} />
      <Text tone="muted">{t('ask.instructor.purpose')}</Text>
      {list ? <Text variant="caption" tone="muted">{t('ask.instructor.fromContent', { count: list.counts.from_content })}</Text> : null}
      <PillGroup
        label={t('ask.instructor.title')}
        value={status}
        options={[
          { value: 'waiting', label: `${t('ask.instructor.waiting')} (${list?.counts.waiting ?? 0})` },
          { value: 'answered', label: `${t('ask.instructor.answeredTab')} (${list?.counts.answered ?? 0})` },
        ]}
        onChange={setStatus}
      />
      {list && list.data.length === 0 ? <EmptyState icon={<Icon icon={Inbox} color="action" size={32} />} title={t('ask.instructor.emptyTitle')} description={t('ask.instructor.empty')} /> : null}
      {(list?.data ?? []).map((q) => (
        <ListRow
          key={q.uuid}
          icon={MessageCircleQuestion}
          title={q.question}
          subtitle={t('ask.instructor.askedBy', { student: q.student, course: q.course })}
          onPress={() => router.push({ pathname: '/instructor/questions/[uuid]', params: { uuid: q.uuid } })}
        />
      ))}
    </Screen>
  );
}
