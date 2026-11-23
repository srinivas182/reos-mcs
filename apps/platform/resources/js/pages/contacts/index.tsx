import { Head, Link, router } from '@inertiajs/react';
import { Search, Sparkles, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AddContactDialog } from '@/components/contacts/add-contact-dialog';
import { ContactPanel } from '@/components/contacts/contact-panel';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { index as connections } from '@/routes/connections';
import { index } from '@/routes/contacts';
import { index as importIndex } from '@/routes/contacts/import';
import type { ContactPanelData, ContactRow, CrmOption, Segment } from '@/types';

type Filters = { segment: Segment; tag: string | null; q: string | null };

type Props = {
    filters: Filters;
    contacts: ContactRow[];
    counts: Record<Segment, number>;
    tags: string[];
    total: number;
    selected: ContactPanelData | null;
    crms: CrmOption[];
};

const SEGMENTS: Segment[] = ['all', 'new', 'active', 'past', 'sphere'];
const th = 'px-3 py-2 text-left font-medium';
const td = 'px-3 py-3 align-top';

/**
 * Contacts, the CRM hub (agent web screen 13, B8.3): every contact from
 * every connected CRM and the phone, once, by tab (segment or the agent's
 * own tags) or search. The table shows stage, last touch, where they come
 * from and the AI next step; opening a row shows the contact panel.
 * Brokers never see these.
 */
export default function ContactsPage({
    filters,
    contacts,
    counts,
    tags,
    total,
    selected,
    crms,
}: Props) {
    const { t, i18n } = useTranslation();
    const [search, setSearch] = useState(filters.q ?? '');

    const visit = (next: Partial<Filters> & { contact?: string | null }) => {
        const query = {
            segment: filters.segment,
            tag: filters.tag,
            q: filters.q,
            contact: selected?.contact.id ?? null,
            ...next,
        };
        router.get(
            index().url,
            Object.fromEntries(
                Object.entries(query).filter(
                    ([key, value]) =>
                        value !== null &&
                        value !== '' &&
                        !(key === 'segment' && value === 'all'),
                ),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const tab = (active: boolean) =>
        cn(
            'flex min-h-12 items-center border-b-2 px-3 text-base whitespace-nowrap focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
            active
                ? 'border-primary font-semibold text-primary'
                : 'border-transparent text-muted-foreground hover:text-foreground',
        );

    const nothingAtAll = counts.all === 0;

    return (
        <>
            <Head title={t('crm.hub.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('crm.hub.title')}
                    description={t('crm.hub.description')}
                    actions={
                        <div className="flex flex-wrap items-center gap-3">
                            <form
                                role="search"
                                className="flex gap-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    visit({
                                        q: search.trim() || null,
                                        contact: null,
                                    });
                                }}
                            >
                                <label
                                    htmlFor="contact-search"
                                    className="sr-only"
                                >
                                    {t('crm.hub.searchLabel')}
                                </label>
                                <Input
                                    id="contact-search"
                                    type="search"
                                    value={search}
                                    maxLength={100}
                                    placeholder={t('crm.hub.search')}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="w-72 max-w-full"
                                />
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="icon"
                                    aria-label={t('crm.hub.searchLabel')}
                                >
                                    <Search aria-hidden />
                                </Button>
                            </form>
                            <Button asChild variant="outline">
                                <Link href={importIndex()}>
                                    {t('crm.import.action')}
                                </Link>
                            </Button>
                            <AddContactDialog crms={crms} />
                        </div>
                    }
                />

                {nothingAtAll ? (
                    <EmptyState
                        icon={Users}
                        title={t('crm.hub.empty.title')}
                        description={t('crm.hub.empty.body')}
                        action={
                            <Button asChild>
                                <Link href={connections()}>
                                    {t('crm.hub.empty.action')}
                                </Link>
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <nav
                            aria-label={t('crm.hub.tabs')}
                            className="border-b border-border"
                        >
                            <ul className="-mb-px flex gap-2 overflow-x-auto">
                                {SEGMENTS.map((segment) => {
                                    const active =
                                        filters.tag === null &&
                                        filters.segment === segment;

                                    return (
                                        <li key={segment}>
                                            <button
                                                type="button"
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                                className={tab(active)}
                                                onClick={() =>
                                                    visit({
                                                        segment,
                                                        tag: null,
                                                        contact: null,
                                                    })
                                                }
                                            >
                                                {t('crm.hub.tabCount', {
                                                    label: t(
                                                        `crm.hub.segments.${segment}`,
                                                    ),
                                                    count: counts[segment],
                                                })}
                                            </button>
                                        </li>
                                    );
                                })}
                                {tags.map((tag) => {
                                    const active = filters.tag === tag;

                                    return (
                                        <li key={`tag-${tag}`}>
                                            <button
                                                type="button"
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                                className={tab(active)}
                                                onClick={() =>
                                                    visit({
                                                        segment: 'all',
                                                        tag,
                                                        contact: null,
                                                    })
                                                }
                                            >
                                                #{tag}
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </nav>

                        <div
                            className={cn(
                                'grid gap-6',
                                selected
                                    ? 'xl:grid-cols-[minmax(0,1fr)_28rem]'
                                    : '',
                            )}
                        >
                            <div className="space-y-2">
                                {contacts.length === 0 ? (
                                    <EmptyState
                                        icon={Search}
                                        title={t('crm.hub.emptyFilter.title')}
                                        description={t(
                                            'crm.hub.emptyFilter.body',
                                        )}
                                    />
                                ) : (
                                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                                        <table className="w-full text-body">
                                            <thead className="border-b border-border text-sm text-muted-foreground">
                                                <tr>
                                                    <th className={th}>
                                                        {t(
                                                            'crm.hub.columns.name',
                                                        )}
                                                    </th>
                                                    <th className={th}>
                                                        {t(
                                                            'crm.hub.columns.stage',
                                                        )}
                                                    </th>
                                                    <th className={th}>
                                                        {t(
                                                            'crm.hub.columns.lastTouch',
                                                        )}
                                                    </th>
                                                    <th className={th}>
                                                        {t(
                                                            'crm.hub.columns.sources',
                                                        )}
                                                    </th>
                                                    <th className={th}>
                                                        {t(
                                                            'crm.hub.columns.nextStep',
                                                        )}
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border">
                                                {contacts.map((row) => {
                                                    const open =
                                                        selected?.contact.id ===
                                                        row.id;

                                                    return (
                                                        <tr
                                                            key={row.id}
                                                            className={
                                                                open
                                                                    ? 'bg-accent'
                                                                    : undefined
                                                            }
                                                        >
                                                            <th
                                                                scope="row"
                                                                className={cn(
                                                                    td,
                                                                    'text-left font-medium',
                                                                )}
                                                            >
                                                                <button
                                                                    type="button"
                                                                    aria-expanded={
                                                                        open
                                                                    }
                                                                    className="min-h-12 text-left text-primary underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                                                    onClick={() =>
                                                                        visit({
                                                                            contact:
                                                                                row.id,
                                                                        })
                                                                    }
                                                                >
                                                                    {row.name}
                                                                </button>
                                                            </th>
                                                            <td className={td}>
                                                                {row.stage ??
                                                                    t(
                                                                        'crm.hub.noStage',
                                                                    )}
                                                            </td>
                                                            <td
                                                                className={cn(
                                                                    td,
                                                                    'whitespace-nowrap',
                                                                )}
                                                            >
                                                                {row.last_touch_at
                                                                    ? timeAgo(
                                                                          row.last_touch_at,
                                                                          i18n.language,
                                                                      )
                                                                    : t(
                                                                          'crm.hub.never',
                                                                      )}
                                                            </td>
                                                            <td className={td}>
                                                                {row.sources
                                                                    .map(
                                                                        (s) =>
                                                                            s.name,
                                                                    )
                                                                    .join(', ')}
                                                            </td>
                                                            <td className={td}>
                                                                {row.next_step ? (
                                                                    <span>
                                                                        <span className="font-medium">
                                                                            {
                                                                                row
                                                                                    .next_step
                                                                                    .text
                                                                            }
                                                                        </span>
                                                                        <span className="block text-sm text-muted-foreground">
                                                                            {
                                                                                row
                                                                                    .next_step
                                                                                    .why
                                                                            }
                                                                        </span>
                                                                    </span>
                                                                ) : (
                                                                    <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                                                        <Sparkles
                                                                            aria-hidden
                                                                            className="size-4"
                                                                        />
                                                                        {t(
                                                                            'crm.hub.askAi',
                                                                        )}
                                                                    </span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {total > contacts.length ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('crm.hub.showing', {
                                            shown: contacts.length,
                                            total,
                                        })}
                                    </p>
                                ) : null}
                            </div>
                            {selected ? (
                                <ContactPanel
                                    panel={selected}
                                    onClose={() => visit({ contact: null })}
                                />
                            ) : null}
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
