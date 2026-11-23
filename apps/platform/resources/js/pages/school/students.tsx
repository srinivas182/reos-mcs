import { Form, Head } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import type { WeeklyReport } from '@/components/practice/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store as storeGroup } from '@/routes/school/groups';

type Row = {
    id: number;
    name: string;
    week_start: string;
    report: WeeklyReport;
    shared_at: string | null;
};

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3';

type ClassGroup = {
    id: number;
    name: string;
    members: number;
    max: number;
    link: string;
};

/**
 * Students (school admin web, A9.2e): the weekly reports candidates have
 * shared with the school; and (A9.3c) class study groups with their invite
 * links: how many joined, never their scores.
 */
export default function StudentsPage({
    reports,
    groups = [],
}: {
    reports: Row[];
    groups?: ClassGroup[];
}) {
    const { t, i18n } = useTranslation();

    return (
        <>
            <Head title={t('practice.reports.students.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('practice.reports.students.title')}
                    description={t('practice.reports.students.description')}
                />
                {reports.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title={t('practice.reports.students.emptyTitle')}
                        description={t('practice.reports.students.empty')}
                    />
                ) : (
                    <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                        <table className="w-full min-w-[48rem] text-base">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'name',
                                            'week',
                                            'minutes',
                                            'questions',
                                            'readiness',
                                            'weakest',
                                        ] as const
                                    ).map((c) => (
                                        <th key={c} scope="col" className={th}>
                                            {t(
                                                `practice.reports.students.columns.${c}`,
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {reports.map((r) => (
                                    <tr key={r.id}>
                                        <th
                                            scope="row"
                                            className={`${td} text-left font-semibold`}
                                        >
                                            {r.name}
                                        </th>
                                        <td className={td}>
                                            {new Date(
                                                `${r.week_start}T12:00:00`,
                                            ).toLocaleDateString(
                                                i18n.language,
                                                {
                                                    month: 'short',
                                                    day: 'numeric',
                                                },
                                            )}
                                        </td>
                                        <td className={td}>
                                            {r.report.minutes} /{' '}
                                            {r.report.goal_minutes}
                                        </td>
                                        <td className={td}>
                                            {r.report.questions}
                                        </td>
                                        <td className={td}>
                                            {r.report.readiness.now === null
                                                ? '—'
                                                : `${r.report.readiness.now}%`}
                                        </td>
                                        <td className={td}>
                                            {r.report.weakest
                                                ? t(
                                                      `practice.topics.${r.report.weakest}`,
                                                  )
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}
                <section
                    aria-labelledby="class-groups"
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                >
                    <div className="space-y-1">
                        <h2 id="class-groups" className="text-lg font-semibold">
                            {t('practice.groups.schoolTitle')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('practice.groups.schoolDescription')}
                        </p>
                    </div>
                    <Form
                        {...storeGroup.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="class-group-name">
                                        {t('practice.groups.name')}
                                    </Label>
                                    <Input
                                        id="class-group-name"
                                        name="name"
                                        required
                                        maxLength={80}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {t('practice.groups.schoolCreate')}
                                </Button>
                            </>
                        )}
                    </Form>
                    {groups.length === 0 ? (
                        <p className="text-body text-muted-foreground">
                            {t('practice.groups.schoolEmpty')}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {groups.map((g) => (
                                <li key={g.id} className="space-y-1 py-3">
                                    <p className="font-semibold">{g.name}</p>
                                    <p className="text-body text-muted-foreground">
                                        {t('practice.groups.members', {
                                            count: g.members,
                                            max: g.max,
                                        })}
                                    </p>
                                    <p className="text-sm break-all text-muted-foreground">
                                        {g.link}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}
