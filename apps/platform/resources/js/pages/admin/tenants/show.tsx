import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import type { PageTab } from '@/components/page-tabs';
import { PageTabs } from '@/components/page-tabs';
import { TenantFeatureSwitches } from '@/components/tenant-feature-switches';
import { TenantLimitsCard } from '@/components/tenant-limits-card';
import { TenantStatusActions } from '@/components/tenant-status-actions';
import { TenantStatusBadge } from '@/components/tenant-status-badge';
import {
    TenantSupportAccess,
    TenantSupportStatus,
} from '@/components/tenant-support-access';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect, tenantTypeLabel } from '@/lib/tenants';
import { timeAgo } from '@/lib/time-ago';
import { index as integrationsIndex } from '@/routes/integrations';
import { index, show, tab as tabRoute, update } from '@/routes/tenants';
import type {
    TenantAuditEvent,
    TenantDetail,
    TenantFeatureKey,
    TenantLimits,
    TenantOptions,
    TenantPlan,
    TenantSupport,
} from '@/types';

type Tab =
    | 'overview'
    | 'plan'
    | 'features'
    | 'integrations'
    | 'domain'
    | 'privacy'
    | 'audit';

type Props = {
    tab: Tab;
    tenant: TenantDetail;
    audit: TenantAuditEvent[];
    options: TenantOptions;
    support: TenantSupport;
};

type Draft = {
    plan: TenantPlan;
    states: string[];
    trial_ends_at: string | null;
    features: Record<TenantFeatureKey, boolean>;
    limits: TenantLimits;
};

const TABS: Tab[] = [
    'overview',
    'plan',
    'features',
    'integrations',
    'domain',
    'privacy',
    'audit',
];

const card = 'rounded-reos-lg border border-border bg-card p-5';

/**
 * Tenant detail (super admin, web journey screen 60): one tenant in tabs.
 * Plan, states, features, limits and retention are edited in place and
 * saved together; status changes ask for a reason; support sign-in needs the
 * owner's consent (A4.1c).
 */
export default function TenantShow({
    tab,
    tenant,
    audit,
    options,
    support,
}: Props) {
    const { t, i18n } = useTranslation();

    const saved: Draft = useMemo(
        () => ({
            plan: tenant.plan,
            states: tenant.states,
            trial_ends_at: tenant.trial_ends_at,
            features: Object.fromEntries(
                tenant.features.map((f) => [f.key, f.on]),
            ) as Draft['features'],
            limits: tenant.limits,
        }),
        [tenant],
    );
    const [draft, setDraft] = useState<Draft>(saved);
    const [saving, setSaving] = useState(false);

    const changes = useMemo(() => {
        const out: Record<string, unknown> = {};
        if (draft.plan !== saved.plan) out.plan = draft.plan;
        if (draft.states.join() !== saved.states.join())
            out.states = draft.states;
        if (draft.trial_ends_at !== saved.trial_ends_at)
            out.trial_ends_at = draft.trial_ends_at;
        const features = Object.fromEntries(
            Object.entries(draft.features).filter(
                ([key, on]) => saved.features[key as TenantFeatureKey] !== on,
            ),
        );
        if (Object.keys(features).length > 0) out.features = features;
        const limits = Object.fromEntries(
            Object.entries(draft.limits).filter(
                ([key, value]) =>
                    saved.limits[key as keyof TenantLimits] !== value,
            ),
        );
        if (Object.keys(limits).length > 0) out.limits = limits;

        return out;
    }, [draft, saved]);
    const dirty = Object.keys(changes).length > 0;

    const save = () =>
        router.patch(update(tenant.id).url, changes as never, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });

    const setLimit = <K extends keyof TenantLimits>(
        key: K,
        value: TenantLimits[K],
    ) => setDraft({ ...draft, limits: { ...draft.limits, [key]: value } });

    const stateName = (code: string) =>
        options.states.find((s) => s.code === code)?.name ?? code;
    const date = (iso: string | null) =>
        iso
            ? new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
              }).format(new Date(iso))
            : '—';

    const tabs: PageTab[] = TABS.map((value) => ({
        title: t(`tenants.show.tabs.${value}`),
        href:
            value === 'overview'
                ? show(tenant.id)
                : tabRoute({ tenant: tenant.id, tab: value }),
        exact: value === 'overview',
    }));

    const used = tenant.members;
    const seats = draft.limits.seats;
    const left = seats === null ? null : Math.max(0, seats - used);

    return (
        <>
            <Head title={tenant.name} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={tenant.name}
                    description={t('tenants.show.description', {
                        type: tenantTypeLabel(t, tenant.type),
                        date: date(tenant.created_at),
                        owner: tenant.owner?.name ?? t('tenants.show.noOwner'),
                    })}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('tenants.show.back')}
                        </Link>
                    }
                    actions={
                        <>
                            <TenantSupportAccess
                                tenant={tenant}
                                support={support}
                            />
                            <TenantStatusActions tenant={tenant} />
                            <Button onClick={save} disabled={!dirty || saving}>
                                {saving
                                    ? t('tenants.show.saving')
                                    : t('tenants.show.save')}
                            </Button>
                        </>
                    }
                />

                {tenant.status_reason &&
                (tenant.status === 'suspended' ||
                    tenant.status === 'archived') ? (
                    <p
                        role="status"
                        className="flex gap-3 rounded-reos-md bg-reos-tone-warning-bg p-4 text-body text-reos-tone-warning-fg"
                    >
                        <AlertTriangle
                            aria-hidden
                            className="mt-0.5 size-5 shrink-0"
                        />
                        {t(
                            tenant.status === 'suspended'
                                ? 'tenants.overview.suspendedBecause'
                                : 'tenants.overview.archivedBecause',
                            {
                                reason: tenant.status_reason,
                            },
                        )}
                    </p>
                ) : null}

                <TenantSupportStatus support={support} />

                <PageTabs tabs={tabs} label={t('tenants.show.tabsLabel')} />

                {tab === 'overview' ? (
                    <section className={card} aria-labelledby="overview-title">
                        <h2 id="overview-title" className="sr-only">
                            {t('tenants.show.tabs.overview')}
                        </h2>
                        <dl className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                            <Fact label={t('tenants.overview.status')}>
                                <TenantStatusBadge status={tenant.status} />
                            </Fact>
                            <Fact label={t('tenants.overview.owner')}>
                                {tenant.owner
                                    ? `${tenant.owner.name} · ${tenant.owner.email}`
                                    : t('tenants.show.noOwner')}
                            </Fact>
                            <Fact label={t('tenants.overview.seats')}>
                                {seats === null
                                    ? t('tenants.overview.seatsUnlimited', {
                                          used,
                                      })
                                    : t('tenants.overview.seatsUsed', {
                                          used,
                                          seats,
                                      })}
                                {seats !== null ? (
                                    <span
                                        className="mt-2 block h-2 rounded-reos-pill bg-muted"
                                        aria-hidden
                                    >
                                        <span
                                            className="block h-2 rounded-reos-pill bg-primary"
                                            style={{
                                                width: `${Math.min(100, (used / Math.max(1, seats)) * 100)}%`,
                                            }}
                                        />
                                    </span>
                                ) : null}
                                {left === 0 ? (
                                    <span className="mt-2 block text-sm text-reos-tone-warning-fg">
                                        {t('tenants.overview.full')}
                                    </span>
                                ) : left !== null &&
                                  seats !== null &&
                                  used / seats >= 0.9 ? (
                                    <span className="mt-2 block text-sm text-reos-tone-warning-fg">
                                        {t('tenants.overview.almostFull', {
                                            count: left,
                                        })}
                                    </span>
                                ) : null}
                            </Fact>
                            <Fact label={t('tenants.overview.lastActive')}>
                                {tenant.last_active_at
                                    ? timeAgo(
                                          tenant.last_active_at,
                                          i18n.language,
                                      )
                                    : t('tenants.overview.never')}
                            </Fact>
                            <Fact label={t('tenants.overview.problems')}>
                                {tenant.connection_problems === 0
                                    ? t('tenants.overview.noProblems')
                                    : tenant.connection_problems}
                            </Fact>
                            <Fact label={t('tenants.overview.states')}>
                                {tenant.states.map(stateName).join(', ') || '—'}
                            </Fact>
                            {tenant.parent ? (
                                <Fact label={t('tenants.overview.parent')}>
                                    {tenant.parent}
                                </Fact>
                            ) : null}
                            {tenant.children.length > 0 ? (
                                <Fact label={t('tenants.overview.teams')}>
                                    <ul className="space-y-1">
                                        {tenant.children.map((child) => (
                                            <li key={child.id}>
                                                <Link
                                                    href={show(child.id)}
                                                    className="text-primary underline-offset-4 hover:underline"
                                                >
                                                    {child.name}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </Fact>
                            ) : null}
                        </dl>
                    </section>
                ) : null}

                {tab === 'plan' ? (
                    <section
                        className={`${card} max-w-3xl space-y-5`}
                        aria-labelledby="plan-title"
                    >
                        <h2
                            id="plan-title"
                            className="text-title font-semibold"
                        >
                            {t('tenants.plan.title')}
                        </h2>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field id="plan" label={t('tenants.plan.plan')}>
                                <select
                                    id="plan"
                                    className={nativeSelect}
                                    value={draft.plan}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            plan: e.target.value as TenantPlan,
                                        })
                                    }
                                >
                                    {options.plans.map((p) => (
                                        <option key={p} value={p}>
                                            {t(`tenants.plans.${p}`)}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            {tenant.status === 'trial' ? (
                                <Field
                                    id="trial-ends"
                                    label={t('tenants.plan.trialEnds')}
                                >
                                    <Input
                                        id="trial-ends"
                                        type="date"
                                        value={draft.trial_ends_at ?? ''}
                                        onChange={(e) =>
                                            setDraft({
                                                ...draft,
                                                trial_ends_at:
                                                    e.target.value || null,
                                            })
                                        }
                                    />
                                </Field>
                            ) : null}
                            <Field
                                id="seats"
                                label={t('tenants.plan.seats')}
                                hint={t('tenants.plan.seatsHint')}
                            >
                                <Input
                                    id="seats"
                                    type="number"
                                    min={1}
                                    inputMode="numeric"
                                    value={draft.limits.seats ?? ''}
                                    onChange={(e) =>
                                        setLimit(
                                            'seats',
                                            e.target.value === ''
                                                ? null
                                                : Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field id="payer" label={t('tenants.plan.payer')}>
                                <select
                                    id="payer"
                                    className={nativeSelect}
                                    value={draft.limits.payer}
                                    onChange={(e) =>
                                        setLimit(
                                            'payer',
                                            e.target
                                                .value as TenantLimits['payer'],
                                        )
                                    }
                                >
                                    {(['organisation', 'member'] as const).map(
                                        (p) => (
                                            <option key={p} value={p}>
                                                {t(`tenants.plan.payers.${p}`)}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                            <Field
                                id="credits"
                                label={t('tenants.plan.creditsPerSeat')}
                            >
                                <Input
                                    id="credits"
                                    type="number"
                                    min={0}
                                    inputMode="numeric"
                                    value={draft.limits.credits_per_seat}
                                    onChange={(e) =>
                                        setLimit(
                                            'credits_per_seat',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="candidate-pool"
                                label={t('tenants.plan.candidatePool')}
                            >
                                <Input
                                    id="candidate-pool"
                                    type="number"
                                    min={0}
                                    inputMode="numeric"
                                    value={draft.limits.candidate_pool}
                                    onChange={(e) =>
                                        setLimit(
                                            'candidate_pool',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                        <label className="flex min-h-12 items-center gap-3">
                            <Checkbox
                                checked={draft.limits.credits_pooled}
                                onCheckedChange={(on) =>
                                    setLimit('credits_pooled', on === true)
                                }
                            />
                            {t('tenants.plan.pooled')}
                        </label>
                        <fieldset>
                            <legend className="mb-2 text-sm font-medium">
                                {t('tenants.new.states')}
                            </legend>
                            <div className="grid gap-2 sm:grid-cols-3">
                                {options.states.map((state) => (
                                    <label
                                        key={state.code}
                                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                                    >
                                        <Checkbox
                                            checked={draft.states.includes(
                                                state.code,
                                            )}
                                            onCheckedChange={(on) =>
                                                setDraft({
                                                    ...draft,
                                                    states: on
                                                        ? [
                                                              ...draft.states,
                                                              state.code,
                                                          ]
                                                        : draft.states.filter(
                                                              (s) =>
                                                                  s !==
                                                                  state.code,
                                                          ),
                                                })
                                            }
                                        />
                                        {state.name}
                                    </label>
                                ))}
                            </div>
                        </fieldset>
                        <p className="text-sm text-muted-foreground">
                            {t('tenants.plan.billingNote')}
                        </p>
                    </section>
                ) : null}

                {tab === 'features' ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <TenantFeatureSwitches
                            features={tenant.features.map((f) => ({
                                ...f,
                                on: draft.features[f.key],
                            }))}
                            onChange={(key, on) =>
                                setDraft({
                                    ...draft,
                                    features: { ...draft.features, [key]: on },
                                })
                            }
                        />
                        <TenantLimitsCard
                            limits={draft.limits}
                            region={tenant.region}
                        />
                    </div>
                ) : null}

                {tab === 'integrations' ? (
                    <div className="grid gap-4 lg:grid-cols-2">
                        <section
                            className={card}
                            aria-labelledby="hidden-title"
                        >
                            <h2
                                id="hidden-title"
                                className="mb-3 font-semibold"
                            >
                                {t('tenants.integrations.hidden')}
                            </h2>
                            {tenant.hidden_tools.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('tenants.integrations.noneHidden')}
                                </p>
                            ) : (
                                <ul className="list-inside list-disc space-y-1">
                                    {tenant.hidden_tools.map((tool) => (
                                        <li key={tool.id}>{tool.name}</li>
                                    ))}
                                </ul>
                            )}
                        </section>
                        <section
                            className={card}
                            aria-labelledby="connected-title"
                        >
                            <h2
                                id="connected-title"
                                className="mb-3 font-semibold"
                            >
                                {t('tenants.integrations.connections')}
                            </h2>
                            {tenant.connections.length === 0 ? (
                                <p className="text-muted-foreground">
                                    {t('tenants.integrations.noConnections')}
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {tenant.connections.map((c) => (
                                        <li
                                            key={c.id}
                                            className="flex justify-between gap-3 py-2"
                                        >
                                            <span>
                                                {c.name}
                                                {c.account ? (
                                                    <span className="block text-sm text-muted-foreground">
                                                        {c.account}
                                                    </span>
                                                ) : null}
                                            </span>
                                            <span className="text-sm">
                                                {t(
                                                    `tenants.integrations.states.${c.status}`,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                            <Link
                                href={integrationsIndex()}
                                className="mt-4 inline-flex min-h-touch items-center text-primary underline-offset-4 hover:underline"
                            >
                                {t('tenants.integrations.catalogue')}
                            </Link>
                        </section>
                    </div>
                ) : null}

                {tab === 'domain' ? (
                    <section
                        className={`${card} max-w-3xl`}
                        aria-labelledby="domain-title"
                    >
                        <h2 id="domain-title" className="sr-only">
                            {t('tenants.show.tabs.domain')}
                        </h2>
                        <dl className="grid gap-6 sm:grid-cols-2">
                            <Fact label={t('tenants.domain.address')}>
                                {tenant.domain ?? '—'}
                            </Fact>
                            <Fact label={t('tenants.domain.custom')}>—</Fact>
                        </dl>
                        <p className="mt-4 text-muted-foreground">
                            {tenant.domain === null
                                ? t('tenants.domain.teamNote')
                                : t('tenants.domain.customNote')}
                        </p>
                    </section>
                ) : null}

                {tab === 'privacy' ? (
                    <section
                        className={`${card} max-w-3xl space-y-5`}
                        aria-labelledby="privacy-title"
                    >
                        <h2 id="privacy-title" className="sr-only">
                            {t('tenants.show.tabs.privacy')}
                        </h2>
                        <dl className="grid gap-6 sm:grid-cols-2">
                            <Fact label={t('tenants.privacy.region')}>
                                {tenant.region}
                            </Fact>
                            <Fact label={t('tenants.privacy.cell')}>
                                {tenant.cell}
                            </Fact>
                        </dl>
                        <p className="text-sm text-muted-foreground">
                            {t('tenants.privacy.cellNote')}
                        </p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field
                                id="logs"
                                label={t('tenants.privacy.logs')}
                                hint={t('tenants.privacy.logsHint')}
                            >
                                <Input
                                    id="logs"
                                    type="number"
                                    min={4}
                                    max={10}
                                    inputMode="numeric"
                                    value={draft.limits.log_retention_years}
                                    onChange={(e) =>
                                        setLimit(
                                            'log_retention_years',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id="deleted"
                                label={t('tenants.privacy.deleted')}
                            >
                                <Input
                                    id="deleted"
                                    type="number"
                                    min={7}
                                    max={90}
                                    inputMode="numeric"
                                    value={draft.limits.deleted_user_days}
                                    onChange={(e) =>
                                        setLimit(
                                            'deleted_user_days',
                                            Number(e.target.value),
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </section>
                ) : null}

                {tab === 'audit' ? (
                    <section className={card} aria-labelledby="audit-title">
                        <h2 id="audit-title" className="mb-3 font-semibold">
                            {t('tenants.audit.title')}
                        </h2>
                        {audit.length === 0 ? (
                            <p className="text-muted-foreground">
                                {t('tenants.audit.empty')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[40rem] text-left text-sm">
                                    <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                {t('tenants.audit.when')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                {t('tenants.audit.who')}
                                            </th>
                                            <th
                                                scope="col"
                                                className="px-3 py-2"
                                            >
                                                {t('tenants.audit.what')}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {audit.map((event) => (
                                            <tr key={event.id}>
                                                <td className="px-3 py-2 whitespace-nowrap">
                                                    {timeAgo(
                                                        event.created_at,
                                                        i18n.language,
                                                    )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {event.actor ??
                                                        t(
                                                            'tenants.audit.system',
                                                        )}
                                                </td>
                                                <td className="px-3 py-2">
                                                    {t(
                                                        `tenants.audit.actions.${event.action}`,
                                                        {
                                                            defaultValue:
                                                                event.action,
                                                            from: event
                                                                .properties
                                                                ?.from
                                                                ? t(
                                                                      `tenants.statuses.${event.properties.from}`,
                                                                      {
                                                                          defaultValue:
                                                                              event
                                                                                  .properties
                                                                                  .from,
                                                                      },
                                                                  )
                                                                : '',
                                                            to: event.properties
                                                                ?.to
                                                                ? t(
                                                                      `tenants.statuses.${event.properties.to}`,
                                                                      {
                                                                          defaultValue:
                                                                              event
                                                                                  .properties
                                                                                  .to,
                                                                      },
                                                                  )
                                                                : '',
                                                        },
                                                    )}
                                                    {event.properties?.changed
                                                        ?.length ? (
                                                        <span className="block text-muted-foreground">
                                                            {t(
                                                                'tenants.audit.changed',
                                                                {
                                                                    fields: event.properties.changed.join(
                                                                        ', ',
                                                                    ),
                                                                },
                                                            )}
                                                        </span>
                                                    ) : null}
                                                    {event.properties
                                                        ?.reason ? (
                                                        <span className="block text-muted-foreground">
                                                            {
                                                                event.properties
                                                                    .reason
                                                            }
                                                        </span>
                                                    ) : null}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                ) : null}
            </div>
        </>
    );
}

function Fact({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="mt-1 font-semibold">{children}</dd>
        </div>
    );
}

function Field({
    id,
    label,
    hint,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p className="text-sm text-muted-foreground">{hint}</p>
            ) : null}
        </div>
    );
}
