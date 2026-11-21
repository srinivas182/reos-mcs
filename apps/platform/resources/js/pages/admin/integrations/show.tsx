import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CirclePause,
    CircleSlash,
    FlaskConical,
    History,
    Radio,
    Sparkles,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import IntegrationCredentialController from '@/actions/App/Http/Controllers/Admin/IntegrationCredentialController';
import { CheckStatus } from '@/components/check-status';
import { ChoiceTiles } from '@/components/choice-tiles';
import type { ChoiceTile } from '@/components/choice-tiles';
import { CopyField } from '@/components/copy-field';
import { CredentialField } from '@/components/credential-field';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import type { PageTab } from '@/components/page-tabs';
import { PageTabs } from '@/components/page-tabs';
import { CatalogueStatusBadge } from '@/components/catalogue-status-badge';
import { Label } from '@/components/ui/label';
import { roleName } from '@/lib/roles';
import InputError from '@/components/input-error';
import { ApprovalBadge, ModeBadge } from '@/components/integration-badges';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { i18n } from '@/lib/i18n';
import { undoToast } from '@/lib/undo-toast';
import {
    index,
    pause as pauseRoute,
    show,
    tab as tabRoute,
} from '@/routes/integrations';
import { update as catalogueRoute } from '@/routes/integrations/catalogue';
import { store as storeCheck } from '@/routes/integrations/checks';
import { update as updateMode } from '@/routes/integrations/mode';
import type {
    AuditActivity,
    CatalogueDetail,
    CatalogueStatus,
    CredentialEnvironment,
    IntegrationDetail,
    IntegrationHealth,
    IntegrationMode,
} from '@/types';
import type { Membership } from '@/types/auth';

type Props = {
    integration: IntegrationDetail;
    activity: AuditActivity[];
    tab: 'availability' | 'credentials' | 'health' | 'changelog';
    catalogue: CatalogueDetail | null;
    health: IntegrationHealth;
};

const modeIcons: Record<IntegrationMode, LucideIcon> = {
    off: CircleSlash,
    simulator: Sparkles,
    sandbox: FlaskConical,
    live: Radio,
};

/**
 * One integration (super admin, web journey screen 65), in tabs:
 * Availability (catalogue status, states, roles, plans, beta organizations,
 * what organizations may do), Credentials (how it runs, keys, the redirect
 * address), Health (connections and sync) and Changelog. The header holds the
 * kill switch. Platform services have no Availability tab.
 */
export default function IntegrationShow({
    integration,
    activity,
    tab,
    catalogue,
    health,
}: Props) {
    const { t } = useTranslation();
    const paused = catalogue?.effective === 'paused';

    const tabs: PageTab[] = [
        ...(catalogue
            ? [
                  {
                      title: t('catalogue.detailTabs.availability'),
                      href: show(integration.id),
                      exact: true,
                  },
              ]
            : []),
        {
            title: t('catalogue.detailTabs.credentials'),
            href: tabRoute({ integration: integration.id, tab: 'credentials' }),
        },
        {
            title: t('catalogue.detailTabs.health'),
            href: tabRoute({ integration: integration.id, tab: 'health' }),
        },
        {
            title: t('catalogue.detailTabs.changelog'),
            href: tabRoute({ integration: integration.id, tab: 'changelog' }),
        },
    ];

    return (
        <>
            <Head title={integration.name} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={integration.name}
                    description={`${t(`integrations.categories.${integration.category}`)} · ${integration.purpose}`}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('integrations.back')}
                        </Link>
                    }
                    actions={
                        catalogue ? (
                            <Button
                                variant={paused ? 'default' : 'outline'}
                                onClick={() =>
                                    router.put(
                                        pauseRoute(integration.id).url,
                                        { paused: !paused },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {paused
                                    ? t('catalogue.resume')
                                    : t('catalogue.pause')}
                            </Button>
                        ) : null
                    }
                />

                <div className="flex flex-wrap gap-2">
                    {catalogue ? (
                        <CatalogueStatusBadge status={catalogue.effective} />
                    ) : null}
                    <ModeBadge
                        mode={integration.mode}
                        paused={integration.paused_at !== null}
                    />
                    <ApprovalBadge status={integration.approval} />
                    {integration.personal_data ? (
                        <Badge variant="outline">
                            {t('integrations.personalData')}
                        </Badge>
                    ) : null}
                </div>

                <PageTabs tabs={tabs} label={t('catalogue.detailTabs.label')} />

                {tab === 'availability' && catalogue ? (
                    <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
                        <AvailabilityForm
                            integrationId={integration.id}
                            catalogue={catalogue}
                        />
                        <SummaryCard
                            integration={integration}
                            catalogue={catalogue}
                            health={health}
                        />
                    </div>
                ) : null}

                {tab === 'credentials' ? (
                    <CredentialsPanel integration={integration} />
                ) : null}

                {tab === 'health' ? (
                    <HealthPanel integration={integration} health={health} />
                ) : null}

                {tab === 'changelog' ? (
                    <ActivityList activity={activity} />
                ) : null}
            </div>
        </>
    );
}

function CredentialsPanel({ integration }: { integration: IntegrationDetail }) {
    const { t } = useTranslation();
    const environments: CredentialEnvironment[] = integration.has_test_system
        ? ['sandbox', 'live']
        : ['live'];

    return (
        <div className="space-y-10">
            <Alert>
                <AlertDescription>
                    {t(`integrations.accounts.${integration.accounts}`)}
                </AlertDescription>
            </Alert>

            <ModeChooser integration={integration} />

            {integration.needs_keys ? (
                environments.map((environment) => (
                    <KeysForm
                        key={environment}
                        integration={integration}
                        environment={environment}
                    />
                ))
            ) : integration.auth === 'none' ? (
                <p className="text-muted-foreground">
                    {t('integrations.keys.noneNeeded')}
                </p>
            ) : null}

            {integration.redirect_url ? (
                <CopyField
                    label={t('integrations.redirect.label')}
                    hint={t('integrations.redirect.hint')}
                    value={integration.redirect_url}
                />
            ) : null}

            {integration.has_test_system ? (
                <section className="space-y-2">
                    <h2 className="text-lg font-semibold">
                        {t('integrations.setup.title')}
                    </h2>
                    <p className="text-muted-foreground">
                        {integration.sandbox_notes}
                    </p>
                </section>
            ) : null}
        </div>
    );
}

function HealthPanel({
    integration,
    health,
}: {
    integration: IntegrationDetail;
    health: IntegrationHealth;
}) {
    const { t } = useTranslation();
    const number = new Intl.NumberFormat(i18n.language);
    const tiles = [
        {
            label: t('catalogue.healthTab.connected'),
            value: number.format(health.connected),
        },
        {
            label: t('catalogue.healthTab.reconnect'),
            value: number.format(health.reconnect),
        },
        {
            label: t('catalogue.healthTab.failing'),
            value: number.format(health.failing),
        },
        {
            label: t('catalogue.healthTab.success'),
            value:
                health.success === null
                    ? t('catalogue.healthTab.noRuns')
                    : `${new Intl.NumberFormat(i18n.language, { maximumFractionDigits: 2 }).format(health.success)}%`,
        },
    ];

    return (
        <section aria-labelledby="health-title" className="space-y-4">
            <h2 id="health-title" className="text-lg font-semibold">
                {t('catalogue.healthTab.title')}
            </h2>
            {integration.paused_at ? (
                <Alert variant="destructive">
                    <CirclePause aria-hidden />
                    <AlertTitle>{t('integrations.paused.title')}</AlertTitle>
                    <AlertDescription>
                        {t('integrations.paused.body', {
                            time: new Intl.DateTimeFormat(i18n.language, {
                                dateStyle: 'medium',
                                timeStyle: 'short',
                            }).format(new Date(integration.paused_at)),
                        })}
                    </AlertDescription>
                </Alert>
            ) : null}
            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {tiles.map((tile) => (
                    <div
                        key={tile.label}
                        className="flex flex-col-reverse rounded-reos-lg border border-border bg-card p-5"
                    >
                        <dt className="text-sm text-muted-foreground">
                            {tile.label}
                        </dt>
                        <dd className="text-2xl font-bold">{tile.value}</dd>
                    </div>
                ))}
            </dl>
            <p className="rounded-reos-md bg-reos-tone-warning-bg p-3 text-sm text-reos-tone-warning-fg">
                {t('catalogue.healthTab.autoPause')}
            </p>
        </section>
    );
}

/** "Credentials and health" beside the rules (journey screen 65). */
function SummaryCard({
    integration,
    catalogue,
    health,
}: {
    integration: IntegrationDetail;
    catalogue: CatalogueDetail;
    health: IntegrationHealth;
}) {
    const { t } = useTranslation();
    const rows: [string, string][] = [
        [
            t('catalogue.summary.auth'),
            t(`catalogue.summary.auths.${catalogue.summary.auth}`),
        ],
        [
            t('catalogue.summary.accounts'),
            t(`catalogue.summary.accountTypes.${catalogue.summary.accounts}`),
        ],
        [
            t('catalogue.summary.runsOn'),
            t(`integrations.mode.${integration.mode}`),
        ],
        [
            t('catalogue.summary.sync'),
            catalogue.built
                ? t('catalogue.summary.syncBuilt')
                : t('catalogue.summary.notBuilt'),
        ],
        [
            t('catalogue.summary.rateLimit'),
            catalogue.summary.rate_limit === null
                ? t('catalogue.none')
                : t('catalogue.summary.perMinute', {
                      count: catalogue.summary.rate_limit,
                  }),
        ],
    ];

    return (
        <section
            aria-labelledby="summary-title"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-6"
        >
            <h2 id="summary-title" className="text-lg font-semibold">
                {t('catalogue.summary.title')}
            </h2>
            <dl className="divide-y divide-border">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex justify-between gap-4 py-2"
                    >
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-semibold">{value}</dd>
                    </div>
                ))}
            </dl>
            <div className="grid grid-cols-2 gap-3">
                <div className="rounded-reos-md border border-border p-3">
                    <p className="text-sm text-muted-foreground">
                        {t('catalogue.healthTab.success')}
                    </p>
                    <p className="text-xl font-bold">
                        {health.success === null
                            ? t('catalogue.healthTab.noRuns')
                            : `${health.success}%`}
                    </p>
                </div>
                <div className="rounded-reos-md border border-border p-3">
                    <p className="text-sm text-muted-foreground">
                        {t('catalogue.healthTab.connected')}
                    </p>
                    <p className="text-xl font-bold">{health.connected}</p>
                </div>
            </div>
        </section>
    );
}

type ListRule = string[] | null;

/** "Everyone" or "Only these", with a checkbox for each choice. */
function RuleField({
    legend,
    value,
    options,
    onChange,
}: {
    legend: string;
    value: ListRule;
    options: { value: string; label: string }[];
    onChange: (value: ListRule) => void;
}) {
    const { t } = useTranslation();
    const id = legend.toLowerCase().replace(/\W+/g, '-');

    return (
        <fieldset className="space-y-2">
            <legend className="font-semibold">{legend}</legend>
            <div className="flex flex-wrap gap-4">
                {(['everyone', 'only'] as const).map((choice) => (
                    <label
                        key={choice}
                        className="flex min-h-12 items-center gap-2"
                    >
                        <input
                            type="radio"
                            name={`${id}-scope`}
                            className="size-5 accent-primary"
                            checked={
                                choice === 'everyone'
                                    ? value === null
                                    : value !== null
                            }
                            onChange={() =>
                                onChange(choice === 'everyone' ? null : [])
                            }
                        />
                        {t(`catalogue.rules.${choice}`)}
                    </label>
                ))}
            </div>
            {value !== null ? (
                <div className="grid max-h-60 gap-1 overflow-y-auto rounded-reos-md border border-border p-3 sm:grid-cols-2 lg:grid-cols-3">
                    {options.map((option) => (
                        <label
                            key={option.value}
                            className="flex min-h-10 items-center gap-2"
                        >
                            <input
                                type="checkbox"
                                className="size-5 accent-primary"
                                checked={value.includes(option.value)}
                                onChange={(event) =>
                                    onChange(
                                        event.target.checked
                                            ? [...value, option.value]
                                            : value.filter(
                                                  (v) => v !== option.value,
                                              ),
                                    )
                                }
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            ) : null}
        </fieldset>
    );
}

function AvailabilityForm({
    integrationId,
    catalogue,
}: {
    integrationId: string;
    catalogue: CatalogueDetail;
}) {
    const { t } = useTranslation();
    const [status, setStatus] = useState<CatalogueStatus | ''>(
        catalogue.status ?? '',
    );
    const [retiresOn, setRetiresOn] = useState(catalogue.retires_on ?? '');
    const [states, setStates] = useState<ListRule>(catalogue.states);
    const [roles, setRoles] = useState<ListRule>(catalogue.roles);
    const [beta, setBeta] = useState<number[]>(catalogue.beta_organisations);
    const [flags, setFlags] = useState({
        tenants_may_hide: catalogue.tenants_may_hide,
        tenants_may_require: catalogue.tenants_may_require,
        show_coming_soon: catalogue.show_coming_soon,
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            catalogueRoute(integrationId).url,
            {
                status: status === '' ? null : status,
                retires_on: status === 'retired' ? retiresOn : null,
                states,
                roles,
                beta_organisations: beta,
                ...flags,
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => setErrors({}),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <section
            aria-labelledby="availability-title"
            className="space-y-6 rounded-reos-lg border border-border bg-card p-6"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 id="availability-title" className="text-lg font-semibold">
                    {t('catalogue.rules.title')}
                </h2>
                <Button onClick={save} disabled={saving}>
                    {t('catalogue.save')}
                </Button>
            </div>

            {!catalogue.built ? (
                <Alert>
                    <AlertDescription>
                        {t('catalogue.rules.notBuilt')}
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="catalogue-status">
                        {t('catalogue.rules.status')}
                    </Label>
                    <select
                        id="catalogue-status"
                        value={status}
                        onChange={(event) =>
                            setStatus(
                                event.target.value as CatalogueStatus | '',
                            )
                        }
                        className="min-h-12 rounded-reos-md border border-input bg-background px-3 text-base"
                    >
                        <option value="">
                            {t('catalogue.rules.statusDefault', {
                                status: t(
                                    `catalogue.statuses.${catalogue.default}`,
                                ),
                            })}
                        </option>
                        {(
                            [
                                'enabled',
                                'beta',
                                'coming_soon',
                                'paused',
                                'retired',
                            ] as const
                        ).map((value) => (
                            <option key={value} value={value}>
                                {t(`catalogue.statuses.${value}`)}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.status} />
                </div>
                {status === 'retired' ? (
                    <div className="grid gap-2">
                        <Label htmlFor="catalogue-retires">
                            {t('catalogue.rules.retiresOn')}
                        </Label>
                        <input
                            id="catalogue-retires"
                            type="date"
                            value={retiresOn}
                            onChange={(event) =>
                                setRetiresOn(event.target.value)
                            }
                            className="min-h-12 rounded-reos-md border border-input bg-background px-3 text-base"
                        />
                        <InputError message={errors.retires_on} />
                    </div>
                ) : null}
            </div>

            <RuleField
                legend={t('catalogue.rules.states')}
                value={states}
                options={catalogue.options.states.map((s) => ({
                    value: s.code,
                    label: s.name,
                }))}
                onChange={setStates}
            />
            <RuleField
                legend={t('catalogue.rules.roles')}
                value={roles}
                options={catalogue.options.roles.map((r) => ({
                    value: r,
                    label: roleName(t, r as Membership['role']),
                }))}
                onChange={setRoles}
            />

            <div className="space-y-1">
                <p className="font-semibold">{t('catalogue.rules.plans')}</p>
                <p className="text-muted-foreground">
                    {t('catalogue.rules.plansLater')}
                </p>
            </div>

            {catalogue.options.organisations.length > 0 ? (
                <fieldset className="space-y-2">
                    <legend className="font-semibold">
                        {t('catalogue.rules.betaOrganisations')}
                    </legend>
                    <div className="grid gap-1 sm:grid-cols-2 lg:grid-cols-3">
                        {catalogue.options.organisations.map((organisation) => (
                            <label
                                key={organisation.id}
                                className="flex min-h-10 items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    className="size-5 accent-primary"
                                    checked={beta.includes(organisation.id)}
                                    onChange={(event) =>
                                        setBeta(
                                            event.target.checked
                                                ? [...beta, organisation.id]
                                                : beta.filter(
                                                      (id) =>
                                                          id !==
                                                          organisation.id,
                                                  ),
                                        )
                                    }
                                />
                                {organisation.name}
                            </label>
                        ))}
                    </div>
                </fieldset>
            ) : null}

            <div className="divide-y divide-border rounded-reos-md border border-border">
                {(
                    [
                        ['tenants_may_hide', 'tenantsMayHide'],
                        ['tenants_may_require', 'tenantsMayRequire'],
                        ['show_coming_soon', 'showComingSoon'],
                    ] as const
                ).map(([key, label]) => (
                    <label
                        key={key}
                        className="flex min-h-12 items-center justify-between gap-4 px-4"
                    >
                        {t(`catalogue.rules.${label}`)}
                        <input
                            type="checkbox"
                            role="switch"
                            className="size-5 accent-primary"
                            checked={flags[key]}
                            onChange={(event) =>
                                setFlags({
                                    ...flags,
                                    [key]: event.target.checked,
                                })
                            }
                        />
                    </label>
                ))}
            </div>
        </section>
    );
}

function ModeChooser({ integration }: { integration: IntegrationDetail }) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;

    const change = (
        mode: IntegrationMode,
        previous?: IntegrationMode,
    ): void => {
        router.patch(
            updateMode(integration.id).url,
            { mode },
            {
                preserveScroll: true,
                onSuccess: () => {
                    if (previous === undefined) {
                        return;
                    }

                    undoToast({
                        message: t('integrations.mode.changed', {
                            mode: t(`integrations.mode.${mode}`),
                        }),
                        onUndo: () => change(previous),
                    });
                },
            },
        );
    };

    const options: ChoiceTile<IntegrationMode>[] = integration.modes.map(
        ({ value, available, reason }) => ({
            value,
            label: t(`integrations.mode.${value}`),
            description: reason
                ? t(reason)
                : t(`integrations.mode.${value}Hint`),
            icon: modeIcons[value],
            // The current mode stays focusable even if its keys changed since.
            disabled: !available && value !== integration.mode,
        }),
    );

    return (
        <section className="space-y-2">
            <ChoiceTiles
                label={t('integrations.mode.label')}
                value={integration.mode}
                options={options}
                onChange={(mode) => {
                    if (mode !== integration.mode) {
                        change(mode, integration.mode);
                    }
                }}
                className="[&_[role=radiogroup]]:lg:grid-cols-2"
            />
            <InputError message={errors.mode} />
        </section>
    );
}

function KeysForm({
    integration,
    environment,
}: {
    integration: IntegrationDetail;
    environment: CredentialEnvironment;
}) {
    const { t } = useTranslation();
    const saved = integration.keys[environment];
    const values = integration.credentials[environment];
    const titleId = `keys-${environment}`;

    return (
        <section
            aria-labelledby={titleId}
            className="space-y-6 rounded-reos-lg border border-border bg-card p-6"
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="space-y-1">
                    <h2 id={titleId} className="text-lg font-semibold">
                        {environment === 'live'
                            ? t('integrations.keys.liveTitle')
                            : t('integrations.keys.sandboxTitle')}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {environment === 'live'
                            ? t('integrations.keys.liveDescription')
                            : t('integrations.keys.sandboxDescription')}
                    </p>
                </div>
                <Badge variant={saved ? 'secondary' : 'outline'}>
                    {saved
                        ? t('integrations.keys.status.saved')
                        : t('integrations.keys.status.missing')}
                </Badge>
            </div>

            <Form
                {...IntegrationCredentialController.update.form({
                    integration: integration.id,
                    environment,
                })}
                options={{ preserveScroll: true }}
                resetOnSuccess
                onSuccess={() =>
                    toast.success(t('integrations.keys.savedToast'))
                }
                className="space-y-5"
            >
                {({ processing, errors }) => (
                    <>
                        {integration.fields.map((field) => {
                            const current = values[field.key];

                            return (
                                <CredentialField
                                    key={`${field.key}-${current?.value ?? ''}`}
                                    name={`values[${field.key}]`}
                                    label={t(
                                        `integrations.fields.${field.key}` as 'integrations.fields.client_id',
                                    )}
                                    kind={field.kind}
                                    secret={field.secret}
                                    required={field.required}
                                    saved={current?.saved ?? false}
                                    last4={current?.last4}
                                    defaultValue={
                                        current?.value ?? field.default
                                    }
                                    error={
                                        (errors as Record<string, string>)[
                                            `values.${field.key}`
                                        ]
                                    }
                                />
                            );
                        })}

                        <p className="text-sm text-muted-foreground">
                            {t('integrations.keys.encrypted')}
                        </p>

                        <CheckStatus check={integration.checks[environment]} />

                        <div className="flex flex-wrap gap-3">
                            <Button type="submit" disabled={processing}>
                                {t('integrations.keys.save')}
                            </Button>
                            {saved ? (
                                <TestConnection
                                    integration={integration}
                                    environment={environment}
                                />
                            ) : null}
                            {saved ? (
                                <RemoveKeys
                                    integration={integration}
                                    environment={environment}
                                />
                            ) : null}
                        </div>
                    </>
                )}
            </Form>
        </section>
    );
}

/** Checks the saved keys with the vendor (a harmless, read-only request). */
function TestConnection({
    integration,
    environment,
}: {
    integration: IntegrationDetail;
    environment: CredentialEnvironment;
}) {
    const { t } = useTranslation();
    const [testing, setTesting] = useState(false);

    return (
        <Button
            type="button"
            variant="secondary"
            disabled={testing}
            aria-busy={testing}
            onClick={() =>
                router.post(
                    storeCheck({ integration: integration.id, environment })
                        .url,
                    {},
                    {
                        preserveScroll: true,
                        onStart: () => setTesting(true),
                        onFinish: () => setTesting(false),
                    },
                )
            }
        >
            {testing
                ? t('integrations.checks.testing')
                : t('integrations.checks.test')}
        </Button>
    );
}

/**
 * Removing keys can't be undone from here (the secrets are never sent back
 * to the browser), so this is one of the few places that asks first.
 */
function RemoveKeys({
    integration,
    environment,
}: {
    integration: IntegrationDetail;
    environment: CredentialEnvironment;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" variant="outline">
                    {t('integrations.keys.remove')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>{t('integrations.keys.removeTitle')}</DialogTitle>
                <DialogDescription>
                    {t('integrations.keys.removeBody')}
                </DialogDescription>
                <DialogFooter className="gap-2">
                    <DialogClose asChild>
                        <Button variant="secondary">
                            {t('common.cancel')}
                        </Button>
                    </DialogClose>
                    <Button
                        variant="destructive"
                        onClick={() =>
                            router.visit(
                                IntegrationCredentialController.destroy({
                                    integration: integration.id,
                                    environment,
                                }),
                                {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        setOpen(false);
                                        toast.success(
                                            t('integrations.keys.removedToast'),
                                        );
                                    },
                                },
                            )
                        }
                    >
                        {t('integrations.keys.remove')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function ActivityList({ activity }: { activity: AuditActivity[] }) {
    const { t } = useTranslation();
    const locale = i18n.language;
    const dates = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
    const lists = new Intl.ListFormat(locale, { type: 'conjunction' });

    const describe = (event: AuditActivity): string => {
        const actor = event.actor ?? t('integrations.activity.unknownActor');
        const environment = event.properties.environment
            ? t(`integrations.environments.${event.properties.environment}`)
            : '';

        switch (event.action) {
            case 'integration.credentials_updated':
                return t('integrations.activity.credentialsUpdated', {
                    actor,
                    environment,
                    fields: lists.format(
                        (event.properties.fields ?? []).map((key) =>
                            t(
                                `integrations.fields.${key}` as 'integrations.fields.client_id',
                            ),
                        ),
                    ),
                });
            case 'integration.credentials_removed':
                return t('integrations.activity.credentialsRemoved', {
                    actor,
                    environment,
                });
            case 'integration.checked':
                return t('integrations.activity.checked', {
                    actor,
                    environment,
                    outcome: t(
                        `integrations.activity.outcomes.${event.properties.outcome ?? 'failed'}`,
                    ),
                });
            case 'integration.resumed':
                return t('integrations.activity.resumed', { actor });
            case 'integration.paused':
                return t('integrations.activity.paused', {
                    count: event.properties.failures ?? 3,
                });
            case 'integration.mode_changed':
                return t('integrations.activity.modeChanged', {
                    actor,
                    from: t(
                        `integrations.mode.${event.properties.from ?? 'simulator'}`,
                    ),
                    to: t(
                        `integrations.mode.${event.properties.to ?? 'simulator'}`,
                    ),
                });
        }
    };

    return (
        <section aria-labelledby="activity-title" className="space-y-3">
            <h2 id="activity-title" className="text-lg font-semibold">
                {t('integrations.activity.title')}
            </h2>
            {activity.length === 0 ? (
                <EmptyState
                    icon={History}
                    title={t('integrations.activity.empty')}
                    description={t('integrations.activity.emptyDescription')}
                />
            ) : (
                <ol className="space-y-3">
                    {activity.map((event) => (
                        <li
                            key={event.id}
                            className="rounded-reos-md border border-border bg-card p-4"
                        >
                            <p className="text-foreground">{describe(event)}</p>
                            <time
                                dateTime={event.created_at}
                                className="text-sm text-muted-foreground"
                            >
                                {dates.format(new Date(event.created_at))}
                            </time>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
