import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Tag } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import type {
    PlanOptions,
    PlanPrice,
    PlanRow,
} from '@/components/plan-dialogs';
import {
    PlanDialog,
    PriceDialog,
    PriceHistoryDialog,
    SplitsDialog,
} from '@/components/plan-dialogs';
import { Button } from '@/components/ui/button';
import type { Buyer, ProductStatus } from '@/lib/billing';
import { priceLabel } from '@/lib/billing';
import { cn } from '@/lib/utils';
import {
    index,
    iosBuyLink as iosBuyLinkRoute,
    status as setStatus,
} from '@/routes/plans';

type Tab =
    | 'agents'
    | 'candidates'
    | 'providers'
    | 'schools'
    | 'associations'
    | 'credit_packs';

const TONE: Record<ProductStatus, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    draft: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    retired: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

const BUYER_FOR: Record<Tab, Buyer> = {
    agents: 'agent',
    candidates: 'candidate',
    providers: 'provider',
    schools: 'school',
    associations: 'association',
    credit_packs: 'agent',
};

/**
 * Plans and pricing (web journey screen 67, B5.1): every plan REOS sells,
 * tabbed by buyer, one row per state it's priced in, with type, price,
 * what's included, AI help and status. New plan, Edit, Set a price and
 * Price history; a price change keeps the old price in the history.
 */
export default function PlansAndPricing({
    tab,
    tabs,
    products,
    options,
    iosBuyLink,
}: {
    tab: Tab;
    tabs: Tab[];
    products: PlanRow[];
    options: PlanOptions;
    iosBuyLink: 'email' | 'link';
}) {
    const { t, i18n } = useTranslation();
    const [editing, setEditing] = useState<PlanRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [pricing, setPricing] = useState<PlanRow | null>(null);
    const [splitting, setSplitting] = useState<PlanRow | null>(null);
    const [history, setHistory] = useState(false);

    const rows = products.flatMap(
        (product): { product: PlanRow; price: PlanPrice | null }[] => {
            const current = product.prices.filter((price) => price.current);

            return current.length === 0
                ? [{ product, price: null }]
                : current.map((price) => ({ product, price }));
        },
    );

    const changeStatus = (product: PlanRow, value: ProductStatus) =>
        router.put(
            setStatus(product.id).url,
            { status: value },
            {
                preserveScroll: true,
                onError: (errors) =>
                    errors.status && toast.error(errors.status),
            },
        );

    return (
        <>
            <Head title={t('plans.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('plans.title')}
                    description={t('plans.description')}
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setHistory(true)}
                            >
                                {t('plans.history')}
                            </Button>
                            <Button onClick={() => setCreating(true)}>
                                <Plus aria-hidden />
                                {t('plans.new')}
                            </Button>
                        </>
                    }
                />

                <nav
                    aria-label={t('plans.title')}
                    className="flex flex-wrap gap-1 border-b border-border"
                >
                    {tabs.map((value) => (
                        <Link
                            key={value}
                            href={index({ query: { tab: value } })}
                            aria-current={value === tab ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-12 items-center border-b-2 px-3 font-medium',
                                value === tab
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {t(`plans.tabs.${value}`)}
                        </Link>
                    ))}
                </nav>

                <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                    {rows.length === 0 ? (
                        <EmptyState
                            icon={Tag}
                            title={t('plans.emptyTitle')}
                            description={t('plans.empty')}
                            action={
                                <Button onClick={() => setCreating(true)}>
                                    {t('plans.new')}
                                </Button>
                            }
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-base">
                                <caption className="sr-only">
                                    {t(`plans.tabs.${tab}`)}
                                </caption>
                                <thead className="text-sm text-muted-foreground uppercase">
                                    <tr className="border-b border-border">
                                        {(
                                            [
                                                'package',
                                                'state',
                                                'type',
                                                'price',
                                                'includes',
                                                'ai',
                                                'status',
                                            ] as const
                                        ).map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className="px-3 py-3 font-semibold"
                                            >
                                                {t(`plans.columns.${column}`)}
                                            </th>
                                        ))}
                                        <th scope="col" className="sr-only">
                                            {t('plans.save')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.map(({ product, price }) => (
                                        <tr
                                            key={`${product.id}-${price?.id ?? 'none'}`}
                                            className="border-b border-border align-top last:border-b-0"
                                        >
                                            <th
                                                scope="row"
                                                className="px-3 py-3 font-medium"
                                            >
                                                {product.name}
                                                {product.seller ? (
                                                    <span className="block text-sm font-normal text-muted-foreground">
                                                        {t('plans.soldBy', {
                                                            name: product.seller
                                                                .name,
                                                        })}
                                                    </span>
                                                ) : null}
                                            </th>
                                            <td className="px-3 py-3">
                                                {price?.state_name ?? '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                {price
                                                    ? t(
                                                          `plans.intervals.${price.interval}`,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-3 whitespace-nowrap">
                                                {price
                                                    ? priceLabel(
                                                          t,
                                                          price.interval,
                                                          price.amount,
                                                          i18n.language,
                                                      )
                                                    : '—'}
                                            </td>
                                            <td className="max-w-xs px-3 py-3 text-muted-foreground">
                                                {product.includes.join(', ') ||
                                                    '—'}
                                            </td>
                                            <td className="px-3 py-3 text-muted-foreground">
                                                {product.ai_help ?? '—'}
                                            </td>
                                            <td className="px-3 py-3">
                                                <label
                                                    className="sr-only"
                                                    htmlFor={`status-${product.id}-${price?.id ?? 'none'}`}
                                                >
                                                    {t('plans.changeStatus', {
                                                        name: product.name,
                                                    })}
                                                </label>
                                                <select
                                                    id={`status-${product.id}-${price?.id ?? 'none'}`}
                                                    value={product.status}
                                                    onChange={(e) =>
                                                        changeStatus(
                                                            product,
                                                            e.target
                                                                .value as ProductStatus,
                                                        )
                                                    }
                                                    className={cn(
                                                        'h-10 rounded-reos-pill border-0 px-3 text-sm font-semibold',
                                                        TONE[product.status],
                                                    )}
                                                >
                                                    {options.statuses.map(
                                                        (value) => (
                                                            <option
                                                                key={value}
                                                                value={value}
                                                            >
                                                                {t(
                                                                    `plans.statuses.${value}`,
                                                                )}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </td>
                                            <td className="px-3 py-3">
                                                <div className="flex gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={t(
                                                            'plans.edit',
                                                            {
                                                                name: product.name,
                                                            },
                                                        )}
                                                        onClick={() =>
                                                            setEditing(product)
                                                        }
                                                    >
                                                        <Pencil aria-hidden />
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        onClick={() =>
                                                            setPricing(product)
                                                        }
                                                    >
                                                        {t('plans.setPrice')}
                                                    </Button>
                                                    {product.seller ? (
                                                        <Button
                                                            variant="outline"
                                                            onClick={() =>
                                                                setSplitting(
                                                                    product,
                                                                )
                                                            }
                                                        >
                                                            {t(
                                                                'plans.splits.open',
                                                            )}
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {tab === 'candidates' ? (
                        <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                            <ShieldCheck
                                aria-hidden
                                className="size-5 shrink-0"
                            />
                            {t('plans.candidatesNote')}
                        </p>
                    ) : null}
                </section>

                <section
                    className="max-w-2xl space-y-3 rounded-reos-lg border border-border bg-card p-5"
                    aria-labelledby="ios-buy-link"
                >
                    <h2 id="ios-buy-link" className="font-semibold">
                        {t('plans.iosBuyLink.title')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t('plans.iosBuyLink.help')}
                    </p>
                    <fieldset className="grid gap-2">
                        <legend className="sr-only">
                            {t('plans.iosBuyLink.title')}
                        </legend>
                        {(['email', 'link'] as const).map((mode) => (
                            <label
                                key={mode}
                                className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                            >
                                <input
                                    type="radio"
                                    name="ios-buy-link"
                                    value={mode}
                                    checked={iosBuyLink === mode}
                                    onChange={() =>
                                        router.put(
                                            iosBuyLinkRoute().url,
                                            { mode },
                                            { preserveScroll: true },
                                        )
                                    }
                                    className="size-5 accent-primary"
                                />
                                {t(`plans.iosBuyLink.${mode}`)}
                            </label>
                        ))}
                    </fieldset>
                </section>
            </div>

            <PlanDialog
                open={creating || editing !== null}
                onOpenChange={(open) =>
                    open ? null : (setCreating(false), setEditing(null))
                }
                plan={editing}
                options={options}
                defaultBuyer={BUYER_FOR[tab]}
            />
            <PriceDialog
                plan={pricing}
                onOpenChange={(open) => (open ? null : setPricing(null))}
                options={options}
            />
            <SplitsDialog
                plan={splitting}
                feePercent={options.fee_percent}
                onOpenChange={(open) => (open ? null : setSplitting(null))}
            />
            <PriceHistoryDialog
                open={history}
                onOpenChange={setHistory}
                plans={products}
            />
        </>
    );
}
