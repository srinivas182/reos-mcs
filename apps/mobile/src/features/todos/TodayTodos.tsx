import { Check, Circle, CloudOff, SquareCheck } from 'lucide-react-native';
import { useState } from 'react';
import { View } from 'react-native';
import { useTranslation } from 'react-i18next';
import { StyleSheet } from 'react-native-unistyles';

import { eventWhen } from '@/lib/calendar';
import { cancelTodoReminder } from '@/lib/todoReminders';
import { setDone, type TodoRow } from '@/lib/todos';
import { Button, Chip, EmptyState, Icon, IconButton, ListRow, SectionTitle, useUndoToast } from '@/ui';

type Props = {
  rows: TodoRow[] | null;
  onChanged: () => void;
  /** Opens capture (the Ask bar's mic does the same). */
  onAdd: () => void;
};

/**
 * Open to-dos on Today (agent app journey, screen 5; B4.3b): each with when
 * it's due and who it's with, a Done button (with Undo), and a chip on any
 * still waiting on the phone for signal.
 */
export function TodayTodos({ rows, onChanged, onAdd }: Props) {
  const { t, i18n } = useTranslation();
  const toast = useUndoToast();
  const [now] = useState(() => Date.now());

  if (rows === null) {
    return null;
  }

  const complete = async (row: TodoRow) => {
    await setDone({ id: row.id, clientId: row.clientId }, true);
    await cancelTodoReminder(row.clientId ?? (row.id === null ? null : String(row.id)));
    onChanged();
    toast({
      message: t('todos.done', { title: row.title }),
      undoneMessage: t('todos.undone'),
      onUndo: async () => {
        await setDone({ id: row.id, clientId: row.clientId }, false);
        onChanged();
      },
    });
  };

  return (
    <View style={styles.section}>
      <SectionTitle>{t('todos.title')}</SectionTitle>
      {rows.length === 0 ? (
        <EmptyState
          icon={<Icon icon={SquareCheck} color="action" size={32} />}
          title={t('todos.emptyTitle')}
          description={t('todos.empty')}
          action={<Button label={t('todos.add')} onPress={onAdd} />}
        />
      ) : (
        rows.map((row) => {
          const overdue = row.due_at !== null && Date.parse(row.due_at) < now;
          const when = row.due_at !== null ? eventWhen(row.due_at, false, i18n.language) : row.due_date !== null ? eventWhen(`${row.due_date}T00:00:00Z`, true, i18n.language) : null;
          const subtitle = [when, row.contact ? t('todos.with', { name: row.contact }) : null].filter(Boolean).join(' · ');

          return (
            <ListRow
              key={row.key}
              icon={row.waiting ? CloudOff : Circle}
              tone={overdue ? 'danger' : 'info'}
              title={row.title}
              subtitle={subtitle || undefined}
              trailing={
                <View style={styles.trailing}>
                  {row.waiting ? <Chip label={t('todos.waiting')} tone="warning" /> : overdue ? <Chip label={t('todos.overdue')} tone="danger" /> : null}
                  <IconButton icon={Check} label={t('todos.markDone', { title: row.title })} onPress={() => void complete(row)} />
                </View>
              }
            />
          );
        })
      )}
    </View>
  );
}

const styles = StyleSheet.create((theme) => ({
  section: { gap: theme.space.sm },
  trailing: { flexDirection: 'row', alignItems: 'center', gap: theme.space.xs },
}));
