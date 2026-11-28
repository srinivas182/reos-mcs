import { router, useFocusEffect } from 'expo-router';
import { BellRing, GraduationCap, UserRound } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { assignCourse, loadCe, remindAllDue, type CeRow, type CeTable } from '@/lib/brokerage';
import { Button, Callout, ChoiceRow, EmptyState, Icon, ListRow, Screen, SectionTitle, Text, TitleBar } from '@/ui';

/**
 * CE compliance on the phone (A16.2): every agent's renewal, hours left and
 * SAE, with Remind all due agents in one action and a course for everyone due.
 */
export default function CeComplianceScreen() {
  const { t, i18n } = useTranslation();
  const [table, setTable] = useState<CeTable | null>(null);
  const [course, setCourse] = useState<number | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ tone: 'success' | 'danger'; text: string } | null>(null);

  useFocusEffect(
    useCallback(() => {
      void loadCe().then((tb) => {
        setTable(tb);
        setCourse(tb?.courses[0]?.id ?? null);
      });
    }, []),
  );

  const status = (r: CeRow): string => {
    const labels: Record<CeRow['status'], string> = {
      done: t('brokerage.ce.statuses.done'),
      on_track: t('brokerage.ce.statuses.on_track'),
      due: t('brokerage.ce.statuses.due'),
      behind: t('brokerage.ce.statuses.behind'),
      overdue: t('brokerage.ce.statuses.overdue'),
      no_licence: t('brokerage.ce.statuses.no_licence'),
    };

    return labels[r.status];
  };
  const run = (work: () => Promise<string>) => {
    setBusy(true);
    setMessage(null);
    work()
      .then((text) => setMessage({ tone: 'success', text }))
      .catch((e: Error) => setMessage({ tone: 'danger', text: e.message }))
      .finally(() => setBusy(false));
  };

  return (
    <Screen>
      <TitleBar title={t('brokerage.ce.title')} onBack={() => router.back()} />
      <Text tone="muted">{table?.scope === 'team' ? t('brokerage.ce.teamPurpose', { team: table.team ?? '' }) : t('brokerage.ce.purpose')}</Text>
      {message ? <Callout tone={message.tone} title={message.text} /> : null}
      {table ? (
        <>
          <Text>{t('brokerage.ce.counts', { due: table.counts.due, agents: table.counts.agents, done: table.counts.done })}</Text>
          <Button
            icon={BellRing}
            label={t('brokerage.ce.remindAll')}
            loading={busy}
            disabled={table.counts.due === 0}
            onPress={() =>
              run(async () => {
                const res = await remindAllDue();
                setTable(res.table);

                return res.sent > 0 ? t('brokerage.ce.reminded', { count: res.sent }) : t('brokerage.ce.remindedNone');
              })
            }
          />
          {table.rows.length === 0 ? <EmptyState icon={<Icon icon={UserRound} color="action" size={32} />} title={t('brokerage.ce.emptyTitle')} description={t('brokerage.ce.empty')} /> : null}
          {table.rows.map((r) => (
            <ListRow
              key={r.id}
              icon={UserRound}
              tone={r.status === 'behind' || r.status === 'overdue' ? 'danger' : r.status === 'due' ? 'warning' : r.status === 'done' ? 'success' : 'info'}
              title={r.team ? `${r.name} · ${r.team}` : r.name}
              subtitle={[
                status(r) + (r.sae ? ` · ${t('brokerage.ce.sae')}` : ''),
                r.due_on ? new Date(`${r.due_on}T12:00:00`).toLocaleDateString(i18n.language) : null,
                r.status === 'no_licence' ? null : t('brokerage.ce.hours', { left: r.hours_left, required: r.hours_required }),
              ]
                .filter(Boolean)
                .join(' · ')}
            />
          ))}
          <SectionTitle>{t('brokerage.ce.assign.title')}</SectionTitle>
          {table.courses.length === 0 ? <Text tone="muted">{t('brokerage.ce.assign.noCourses')}</Text> : null}
          {table.courses.map((c) => (
            <ChoiceRow key={c.id} title={c.title} detail={c.hours ?? undefined} selected={course === c.id} onPress={() => setCourse(c.id)} />
          ))}
          {table.courses.length > 0 ? (
            <Button
              variant="secondary"
              icon={GraduationCap}
              label={`${t('brokerage.ce.assign.submit')} · ${t('brokerage.ce.assign.due')}`}
              disabled={busy || course === null || table.counts.due === 0}
              onPress={() =>
                run(async () => {
                  const res = await assignCourse(course ?? 0, null);
                  setTable(res.table);

                  return t('brokerage.ce.assign.assigned', { count: res.assigned });
                })
              }
            />
          ) : null}
        </>
      ) : null}
    </Screen>
  );
}
