import { Head } from '@inertiajs/react';
import { LayoutGrid, Plus, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import type {
    CategoryOptions,
    CategoryStatus,
    ProviderCategoryRow,
} from '@/components/provider-category-dialog';
import { ProviderCategoryDialog } from '@/components/provider-category-dialog';
import { Button } from '@/components/ui/button';
import { money } from '@/lib/billing';
import { cn } from '@/lib/utils';

const TONE: Record<CategoryStatus, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    draft: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    requested: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    retired: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

/**
 * Service provider categories (web journey screen 68, A6.1): unlimited
 * categories, each with its licence check, providers, Texas plan prices and
 * status; the selected category's settings beside the table. Add category
 * and edit settings. RESPA: flat fees only.
 */
export default function ProviderCategories({
    categories,
    options,
}: {
    categories: ProviderCategoryRow[];
    options: CategoryOptions;
}) {
    const { t, i18n } = useTranslation();
    const [selected, setSelected] = useState<ProviderCategoryRow | null>(
        categories[0] ?? null,
    );
    const [editing, setEditing] = useState<ProviderCategoryRow | null>(null);
    const [creating, setCreating] = useState(false);
    const texas = (row: ProviderCategoryRow) => {
        const prices = (['basic', 'pro', 'premium'] as const)
            .map((tier) =>
                row.prices.find((p) => p.state === 'TX' && p.tier === tier),
            )
            .filter((p) => p !== undefined)
            .map((p) => money(p.monthly, i18n.language));

        return prices.length === 0 ? '—' : prices.join(' · ');
    };
    const current =
        categories.find((c) => c.id === selected?.id) ?? categories[0] ?? null;

    return (
        <>
            <Head title={t('providerCategories.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('providerCategories.title')}
                    description={t('providerCategories.description')}
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('providerCategories.add')}
                        </Button>
                    }
                />
                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                    <section className="rounded-reos-lg border border-border bg-card p-5">
                        {categories.length === 0 ? (
                            <EmptyState
                                icon={LayoutGrid}
                                title={t('providerCategories.emptyTitle')}
                                description={t('providerCategories.empty')}
                            />
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-base">
                                    <caption className="sr-only">
                                        {t('providerCategories.title')}
                                    </caption>
                                    <thead className="text-sm text-muted-foreground uppercase">
                                        <tr className="border-b border-border">
                                            {(
                                                [
                                                    'category',
                                                    'licence',
                                                    'providers',
                                                    'prices',
                                                    'status',
                                                ] as const
                                            ).map((c) => (
                                                <th
                                                    key={c}
                                                    scope="col"
                                                    className="px-3 py-3 font-semibold"
                                                >
                                                    {t(
                                                        `providerCategories.columns.${c}`,
                                                    )}
                                                </th>
                                            ))}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {categories.map((row) => (
                                            <tr
                                                key={row.id}
                                                aria-selected={
                                                    current?.id === row.id
                                                }
                                                className={cn(
                                                    'border-b border-border align-top last:border-b-0',
                                                    current?.id === row.id &&
                                                        'bg-accent',
                                                )}
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-3 py-3 font-medium"
                                                >
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setSelected(row)
                                                        }
                                                        className="min-h-12 text-left hover:underline"
                                                    >
                                                        {row.name}
                                                    </button>
                                                </th>
                                                <td className="px-3 py-3">
                                                    {row.licence_check ?? '—'}
                                                </td>
                                                <td className="px-3 py-3 tabular-nums">
                                                    {row.status === 'requested'
                                                        ? '—'
                                                        : row.providers}
                                                </td>
                                                <td className="px-3 py-3 whitespace-nowrap">
                                                    {texas(row)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <span
                                                        className={cn(
                                                            'rounded-full px-2.5 py-0.5 text-sm font-medium whitespace-nowrap',
                                                            TONE[row.status],
                                                        )}
                                                    >
                                                        {row.status ===
                                                        'requested'
                                                            ? t(
                                                                  'providerCategories.requested',
                                                                  {
                                                                      count: row.requests,
                                                                  },
                                                              )
                                                            : t(
                                                                  `providerCategories.statuses.${row.status}`,
                                                              )}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                    {current ? (
                        <section
                            aria-labelledby="category-settings"
                            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <h2
                                    id="category-settings"
                                    className="font-semibold"
                                >
                                    {t('providerCategories.settings', {
                                        name: current.name,
                                    })}
                                </h2>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setEditing(current)}
                                >
                                    {t('providerCategories.editSettings')}
                                </Button>
                            </div>
                            <dl className="divide-y divide-border">
                                {(
                                    [
                                        [
                                            'verification',
                                            current.verification ?? '—',
                                        ],
                                        [
                                            'profileFields',
                                            current.profile_fields
                                                .map(
                                                    (f) =>
                                                        `${f.label} (${t(`providerCategories.fieldTypes.${f.type}`)})`,
                                                )
                                                .join(', ') || '—',
                                        ],
                                        [
                                            'permissions',
                                            current.special_permissions ?? '—',
                                        ],
                                        [
                                            'areasOnPro',
                                            String(current.areas_on_pro),
                                        ],
                                    ] as const
                                ).map(([key, value]) => (
                                    <div
                                        key={key}
                                        className="flex justify-between gap-4 py-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {t(
                                                `providerCategories.fields.${key}`,
                                            )}
                                        </dt>
                                        <dd className="text-right font-medium">
                                            {value}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {current.request_notes.length > 0 ? (
                                <div className="space-y-1">
                                    <h3 className="font-medium">
                                        {t('providerCategories.requestNotes')}
                                    </h3>
                                    <ul className="list-disc space-y-1 pl-5 text-base">
                                        {current.request_notes.map((note) => (
                                            <li key={note}>{note}</li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}
                            <p className="flex gap-2 rounded-reos-md bg-reos-tone-warning-bg p-3 text-sm text-reos-tone-warning-fg">
                                <TriangleAlert
                                    aria-hidden
                                    className="size-5 shrink-0"
                                />
                                {t('providerCategories.respa')}
                            </p>
                        </section>
                    ) : null}
                </div>
            </div>
            <ProviderCategoryDialog
                open={creating || editing !== null}
                category={editing}
                options={options}
                onOpenChange={(open) =>
                    open ? null : (setCreating(false), setEditing(null))
                }
            />
        </>
    );
}
