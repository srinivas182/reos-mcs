import { Head, router } from '@inertiajs/react';
import { ArrowRight, SearchX } from 'lucide-react';
import { useDeferredValue, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { update } from '@/routes/states';

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
};

type Props = { states: StateRow[]; statuses: StateStatus[] };

const tone: Record<StateStatus, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    in_preparation: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    waitlist: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    not_planned: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/**
 * States (web journey screen 61): which states are live, being prepared or
 * on the waitlist, and how many people wait for each. Status changes are
 * audited and can be undone.
 */
export default function StatesIndex({ states, statuses }: Props) {
    const { t, i18n } = useTranslation();
    const [query, setQuery] = useState('');
    const search = useDeferredValue(query.trim().toLowerCase());
    const number = new Intl.NumberFormat(i18n.language);
    const live = states.filter((s) => s.status === 'live').map((s) => s.name);

    const rows = useMemo(
        () =>
            states.filter(
                (s) =>
                    search === '' ||
                    s.name.toLowerCase().includes(search) ||
                    s.code.toLowerCase() === search,
            ),
        [states, search],
    );

    const change = (state: StateRow, status: StateStatus, undo = true) =>
        router.patch(
            update(state.code).url,
            { status },
            {
                preserveScroll: true,
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
                                            {state.name}
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

                <section className="max-w-2xl space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="font-semibold">
                        {t('states.goesLive.title')}
                    </h2>
                    <ul className="space-y-2 text-body">
                        {(['register', 'waitlist', 'fill'] as const).map(
                            (key) => (
                                <li key={key} className="flex gap-2">
                                    <ArrowRight
                                        aria-hidden
                                        className="mt-1 size-4 shrink-0 text-primary"
                                    />
                                    {t(`states.goesLive.${key}`)}
                                </li>
                            ),
                        )}
                    </ul>
                </section>
            </div>
        </>
    );
}
