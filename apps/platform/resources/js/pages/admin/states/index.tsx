import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Plus, SearchX } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { AddStateDialog, LaunchChecklist } from '@/components/state-launch';
import type { ChecklistItem } from '@/components/state-launch';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { update } from '@/routes/states';
import { show as showRules } from '@/routes/states/rules';

type StateStatus = 'live' | 'in_preparation' | 'waitlist' | 'not_planned';

type StateRow = {
    code: string;
    name: string;
    status: StateStatus;
    regulator: string | null;
    exam_vendor: string | null;
    qualifying_hours: number | null;
    ce_hours: number | null;
    waiting: number;
    courses_approved: number;
    checklist: Record<string, boolean>;
    went_live_at: string | null;
};

type Props = {
    states: StateRow[];
    statuses: StateStatus[];
    checklistItems: ChecklistItem[];
};

const tone: Record<StateStatus, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    in_preparation: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    waitlist: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    not_planned: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/** Live, being prepared, or with people waiting: shown first; the rest on request. */
const active = (s: StateRow) =>
    s.status === 'live' || s.status === 'in_preparation' || s.waiting > 0;

/**
 * States (web journey screen 61): which states are live, being prepared or
 * on the waitlist, how many people wait for each, and the launch checklist
 * a state completes before it goes live (A5.1). Status changes are audited
 * and can be undone; going live tells the state's waitlist.
 */
export default function StatesIndex({
    states,
    statuses,
    checklistItems,
}: Props) {
    const { t, i18n } = useTranslation();
    const [query, setQuery] = useState('');
    const [adding, setAdding] = useState(false);
    const [chosen, setChosen] = useState<string | null>(null);
    const preparing =
        states.find((s) => s.code === chosen) ??
        states.find((s) => s.status === 'in_preparation') ??
        null;
    const search = useDeferredValue(query.trim().toLowerCase());
    const number = new Intl.NumberFormat(i18n.language);
    const live = states.filter((s) => s.status === 'live').map((s) => s.name);

    const [showAll, setShowAll] = useState(false);
    const hidden = states.filter((s) => !active(s)).length;
    const rows = useMemo(
        () =>
            states.filter((s) =>
                search === ''
                    ? showAll || active(s)
                    : s.name.toLowerCase().includes(search) ||
                      s.code.toLowerCase() === search,
            ),
        [states, search, showAll],
    );

    const change = (state: StateRow, status: StateStatus, undo = true) =>
        router.patch(
            update(state.code).url,
            { status },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.status) {
                        toast.error(errors.status);
                    }
                },
                onSuccess: () => {
                    if (!undo) {
                        return;
                    }

                    undoToast({
                        message: t('states.changed', {
                            state: state.name,
                            status: t(`states.statuses.${status}`),
                        }),
                        onUndo: () => change(state, state.status, false),
                    });
                },
            },
        );

    return (
        <>
            <Head title={t('states.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('states.title')}
                    description={`${t('states.description')}${live.length ? ` · ${live.join(', ')}: ${t('states.statuses.live')}` : ''}`}
                    actions={
                        <Button onClick={() => setAdding(true)}>
                            <Plus aria-hidden />
                            {t('states.add.button')}
                        </Button>
                    }
                />

                <div className="grid max-w-md gap-2">
                    <Label htmlFor="state-search">{t('states.search')}</Label>
                    <Input
                        id="state-search"
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                {rows.length === 0 ? (
                    <EmptyState
                        icon={SearchX}
                        title={t('states.noResults', { search: query })}
                        description={t('states.description')}
                    />
                ) : (
                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                        <table className="w-full text-left text-base">
                            <thead className="text-sm text-muted-foreground uppercase">
                                <tr className="border-b border-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('states.columns.state')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('states.columns.status')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('states.columns.regulator')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('states.columns.examVendor')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('states.columns.qualifyingHours')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('states.columns.ceHours')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('states.columns.coursesApproved')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('states.columns.waitlist')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((state) => (
                                    <tr
                                        key={state.code}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <th
                                            scope="row"
                                            className="px-4 py-2 font-medium"
                                        >
                                            {state.status === 'live' ? (
                                                <Link
                                                    href={showRules(state.code)}
                                                    className="inline-flex min-h-12 items-center font-medium text-primary underline-offset-4 hover:underline"
                                                    aria-label={t(
                                                        'states.rulesFor',
                                                        { state: state.name },
                                                    )}
                                                >
                                                    {state.name}
                                                </Link>
                                            ) : (
                                                <button
                                                    type="button"
                                                    className="min-h-12 text-left font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                    aria-label={t(
                                                        'states.checklist.open',
                                                        { state: state.name },
                                                    )}
                                                    onClick={() =>
                                                        setChosen(state.code)
                                                    }
                                                >
                                                    {state.name}
                                                </button>
                                            )}
                                        </th>
                                        <td className="px-4 py-2">
                                            <label
                                                className="sr-only"
                                                htmlFor={`status-${state.code}`}
                                            >
                                                {t('states.changeStatus', {
                                                    state: state.name,
                                                })}
                                            </label>
                                            <select
                                                id={`status-${state.code}`}
                                                value={state.status}
                                                onChange={(event) =>
                                                    change(
                                                        state,
                                                        event.target
                                                            .value as StateStatus,
                                                    )
                                                }
                                                className={cn(
                                                    'h-10 rounded-reos-pill border-0 px-3 text-sm font-semibold focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                                                    tone[state.status],
                                                )}
                                            >
                                                {statuses.map((status) => (
                                                    <option
                                                        key={status}
                                                        value={status}
                                                    >
                                                        {t(
                                                            `states.statuses.${status}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="px-4 py-2">
                                            {state.regulator ?? '—'}
                                        </td>
                                        <td className="px-4 py-2">
                                            {state.exam_vendor ?? '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {state.qualifying_hours ?? '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {state.ce_hours ?? '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {state.courses_approved}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {state.waiting > 0
                                                ? number.format(state.waiting)
                                                : '—'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {search === '' && hidden > 0 ? (
                    <Button
                        variant="outline"
                        onClick={() => setShowAll(!showAll)}
                    >
                        {showAll
                            ? t('states.showActive')
                            : t('states.showAll', { count: hidden })}
                    </Button>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    {preparing ? (
                        <LaunchChecklist
                            state={preparing}
                            items={checklistItems}
                        />
                    ) : null}
                    <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="font-semibold">
                            {t('states.goesLive.title')}
                        </h2>
                        <ul className="space-y-2 text-body">
                            {(
                                [
                                    'register',
                                    'switcher',
                                    'content',
                                    'licences',
                                ] as const
                            ).map((key) => (
                                <li key={key} className="flex gap-2">
                                    <ArrowRight
                                        aria-hidden
                                        className="mt-1 size-4 shrink-0 text-primary"
                                    />
                                    {t(`states.goesLive.${key}`)}
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>

                <AddStateDialog
                    open={adding}
                    onOpenChange={setAdding}
                    states={states.filter((s) => s.status !== 'live')}
                />
            </div>
        </>
    );
}
