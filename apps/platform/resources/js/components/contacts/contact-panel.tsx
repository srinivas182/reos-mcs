import { router, useForm } from '@inertiajs/react';
import { ListChecks, StickyNote, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { InsightCard } from '@/components/contacts/insight-card';
import { ReachDialog } from '@/components/contacts/reach-dialog';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { timeAgo } from '@/lib/time-ago';
import { insight as askInsight } from '@/routes/contacts';
import { store as storeNote } from '@/routes/contacts/notes';
import { store as storeTask } from '@/routes/contacts/tasks';
import { store as storeTouch } from '@/routes/contacts/touches';
import type { ContactPanelData } from '@/types';

type Props = {
    panel: ContactPanelData;
    onClose: () => void;
};

const section = 'space-y-3';
const heading = 'text-base font-semibold';

/**
 * The contact panel (screen 13, B8.3): details, where they come from,
 * the AI summary and next step, notes and tasks (written back to the
 * source CRMs), and recent sync activity. Call and Text happen on the
 * agent's own phone.
 */
export function ContactPanel({ panel, onClose }: Props) {
    const { t, i18n } = useTranslation();
    const { contact } = panel;
    const [asking, setAsking] = useState(false);
    const phone = contact.phones[0] ?? null;
    const reload = { preserveScroll: true, preserveState: true };
    const note = useForm({ body: '' });
    const task = useForm({ title: '', due_date: '' });
    const date = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeZone: 'UTC',
        }).format(new Date(iso));

    const logTouch = (channel: 'call' | 'text', body: string | null) =>
        router.post(storeTouch(contact.id).url, { channel, body }, reload);

    return (
        <aside
            aria-labelledby="contact-name"
            className="space-y-6 rounded-reos-lg border border-border bg-background p-5"
        >
            <header className="flex items-start justify-between gap-3">
                <div className="min-w-0 space-y-1">
                    <h2 id="contact-name" className="text-xl font-semibold">
                        {contact.name}
                    </h2>
                    <p className="text-body text-muted-foreground">
                        {contact.stage ?? t('crm.hub.noStage')}
                        {panel.segment
                            ? ` · ${t(`crm.hub.segments.${panel.segment}`)}`
                            : ''}
                    </p>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('crm.hub.close')}
                    onClick={onClose}
                >
                    <X aria-hidden />
                </Button>
            </header>

            <div className="flex flex-wrap gap-3">
                {phone ? (
                    <>
                        <ReachDialog
                            channel="call"
                            name={contact.name}
                            phone={phone}
                            onLogged={() => logTouch('call', null)}
                        />
                        <ReachDialog
                            channel="text"
                            name={contact.name}
                            phone={phone}
                            draft={
                                panel.insight?.channel === 'text'
                                    ? panel.insight.draft
                                    : null
                            }
                            onLogged={(body) => logTouch('text', body)}
                        />
                    </>
                ) : (
                    <p className="text-body text-muted-foreground">
                        {t('crm.hub.noPhone')}
                    </p>
                )}
            </div>

            <InsightCard
                insight={panel.insight}
                busy={asking}
                onAsk={() =>
                    router.post(
                        askInsight(contact.id).url,
                        {},
                        {
                            ...reload,
                            onStart: () => setAsking(true),
                            onFinish: () => setAsking(false),
                        },
                    )
                }
            />

            <section className={section} aria-labelledby="contact-details">
                <h3 id="contact-details" className={heading}>
                    {t('crm.hub.details')}
                </h3>
                <dl className="grid gap-3 text-body sm:grid-cols-2">
                    {contact.emails.length > 0 ? (
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('crm.hub.email')}
                            </dt>
                            {contact.emails.map((email) => (
                                <dd key={email} className="break-all">
                                    <a
                                        className="text-primary underline underline-offset-4"
                                        href={`mailto:${email}`}
                                    >
                                        {email}
                                    </a>
                                </dd>
                            ))}
                        </div>
                    ) : null}
                    {contact.phones.length > 0 ? (
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('crm.hub.phone')}
                            </dt>
                            {contact.phones.map((number) => (
                                <dd key={number} className="tabular-nums">
                                    {number}
                                </dd>
                            ))}
                        </div>
                    ) : null}
                    {contact.birthday ? (
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('crm.hub.birthday')}
                            </dt>
                            <dd>{date(contact.birthday)}</dd>
                        </div>
                    ) : null}
                    {panel.address ? (
                        <div>
                            <dt className="text-sm text-muted-foreground">
                                {t('crm.hub.address')}
                            </dt>
                            <dd>
                                {Object.values(panel.address)
                                    .filter(Boolean)
                                    .join(', ')}
                            </dd>
                        </div>
                    ) : null}
                </dl>
                <h4 className="text-sm font-medium text-muted-foreground">
                    {t('crm.contact.sources')}
                </h4>
                <ul className="space-y-1 text-body">
                    {contact.sources.map((source) => (
                        <li key={source.source}>
                            {source.name}
                            {source.synced_at ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {timeAgo(source.synced_at, i18n.language)}
                                </span>
                            ) : null}
                            {source.campaign ? (
                                <span className="block text-sm text-muted-foreground">
                                    {t('crm.contact.campaign', {
                                        source: source.name,
                                        name: source.campaign,
                                        status: source.campaign_status ?? '',
                                    })}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            </section>

            <section className={section} aria-labelledby="contact-notes">
                <h3 id="contact-notes" className={heading}>
                    {t('crm.contact.notes')}
                </h3>
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        note.post(storeNote(contact.id).url, {
                            ...reload,
                            onSuccess: () => note.reset(),
                        });
                    }}
                >
                    <Label htmlFor="note-body">
                        {t('crm.contact.addNote')}
                    </Label>
                    <Textarea
                        id="note-body"
                        value={note.data.body}
                        maxLength={20000}
                        onChange={(e) => note.setData('body', e.target.value)}
                    />
                    <InputError message={note.errors.body} />
                    <Button
                        type="submit"
                        disabled={
                            note.processing || note.data.body.trim() === ''
                        }
                    >
                        {t('crm.contact.save')}
                    </Button>
                </form>
                {panel.notes.length === 0 ? (
                    <EmptyState
                        icon={StickyNote}
                        title={t('crm.contact.noNotes')}
                        description={t('crm.contact.noNotesBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {panel.notes.map((n) => (
                            <li
                                key={n.id}
                                className="rounded-reos-md border border-border p-3"
                            >
                                <p className="text-body whitespace-pre-line">
                                    {n.body}
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {t('crm.contact.from', {
                                        source: n.source_name,
                                    })}
                                    {n.at
                                        ? ` · ${timeAgo(n.at, i18n.language)}`
                                        : ''}
                                    {n.sent_to.length > 0
                                        ? ` · ${t('crm.contact.sentTo', { tools: n.sent_to.join(', ') })}`
                                        : ''}
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section className={section} aria-labelledby="contact-tasks">
                <h3 id="contact-tasks" className={heading}>
                    {t('crm.hub.tasks')}
                </h3>
                <form
                    className="grid gap-3 sm:grid-cols-[1fr_auto]"
                    onSubmit={(e) => {
                        e.preventDefault();
                        task.post(storeTask(contact.id).url, {
                            ...reload,
                            onSuccess: () => task.reset(),
                        });
                    }}
                >
                    <div className="space-y-2">
                        <Label htmlFor="task-title">
                            {t('crm.hub.addTask')}
                        </Label>
                        <Input
                            id="task-title"
                            value={task.data.title}
                            maxLength={200}
                            onChange={(e) =>
                                task.setData('title', e.target.value)
                            }
                        />
                        <InputError message={task.errors.title} />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="task-due">
                            {t('crm.hub.dueLabel')}
                        </Label>
                        <Input
                            id="task-due"
                            type="date"
                            value={task.data.due_date}
                            onChange={(e) =>
                                task.setData('due_date', e.target.value)
                            }
                        />
                        <InputError message={task.errors.due_date} />
                    </div>
                    <Button
                        type="submit"
                        className="sm:col-span-2 sm:justify-self-start"
                        disabled={
                            task.processing || task.data.title.trim() === ''
                        }
                    >
                        {t('crm.hub.saveTask')}
                    </Button>
                </form>
                {panel.tasks.length === 0 ? (
                    <EmptyState
                        icon={ListChecks}
                        title={t('crm.hub.noTasks')}
                        description={t('crm.hub.noTasksBody')}
                    />
                ) : (
                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                        {panel.tasks.map((item) => {
                            const due = item.due_date ?? item.due_at;

                            return (
                                <li
                                    key={item.id}
                                    className="flex items-center justify-between gap-3 p-3"
                                >
                                    <span
                                        className={
                                            item.done
                                                ? 'text-body text-muted-foreground line-through'
                                                : 'text-body'
                                        }
                                    >
                                        {item.title}
                                    </span>
                                    <span className="text-sm whitespace-nowrap text-muted-foreground">
                                        {item.done
                                            ? t('crm.hub.taskDone')
                                            : due
                                              ? t('crm.hub.dueOn', {
                                                    date: date(due),
                                                })
                                              : t('crm.hub.taskOpen')}
                                    </span>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            <section className={section} aria-labelledby="contact-activity">
                <h3 id="contact-activity" className={heading}>
                    {t('crm.hub.activity')}
                </h3>
                {panel.activity.length === 0 ? (
                    <p className="text-body text-muted-foreground">
                        {t('crm.hub.noActivity')}
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {panel.activity.map((line) => (
                            <li key={line.id} className="text-body">
                                {line.text}
                                <span className="block text-sm text-muted-foreground">
                                    {timeAgo(line.occurred_at, i18n.language)}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </aside>
    );
}
