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
import IntegrationCredentialController from '@/actions/App/Http/Controllers/Settings/IntegrationCredentialController';
import { CheckStatus } from '@/components/check-status';
import { ChoiceTiles } from '@/components/choice-tiles';
import type { ChoiceTile } from '@/components/choice-tiles';
import { CopyField } from '@/components/copy-field';
import { CredentialField } from '@/components/credential-field';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
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
import { index } from '@/routes/integrations';
import { store as storeCheck } from '@/routes/integrations/checks';
import { update as updateMode } from '@/routes/integrations/mode';
import type {
    AuditActivity,
    CredentialEnvironment,
    IntegrationDetail,
    IntegrationMode,
} from '@/types';

type Props = {
    integration: IntegrationDetail;
    activity: AuditActivity[];
};

const modeIcons: Record<IntegrationMode, LucideIcon> = {
    off: CircleSlash,
    simulator: Sparkles,
    sandbox: FlaskConical,
    live: Radio,
};

/**
 * One integration (super admin): how it runs, its test and live keys, the
 * redirect address to register with the vendor, and recent changes.
 */
export default function IntegrationShow({ integration, activity }: Props) {
    const { t } = useTranslation();

    const environments: CredentialEnvironment[] = integration.has_test_system
        ? ['sandbox', 'live']
        : ['live'];

    return (
        <>
            <Head title={integration.name} />

            <h1 className="sr-only">{integration.name}</h1>

            <div className="space-y-10">
                <div className="space-y-4">
                    <Link
                        href={index()}
                        className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                    >
                        <ArrowLeft aria-hidden className="size-5" />
                        {t('integrations.back')}
                    </Link>

                    <Heading
                        variant="small"
                        title={integration.name}
                        description={integration.purpose}
                    />

                    <div className="flex flex-wrap gap-2">
                        <ModeBadge
                            mode={integration.mode}
                            paused={integration.paused_at !== null}
                        />
                        <Badge variant="outline">
                            {t(
                                `integrations.categories.${integration.category}`,
                            )}
                        </Badge>
                        <ApprovalBadge status={integration.approval} />
                        {integration.personal_data ? (
                            <Badge variant="outline">
                                {t('integrations.personalData')}
                            </Badge>
                        ) : null}
                    </div>

                    <Alert>
                        <AlertDescription>
                            {t(`integrations.accounts.${integration.accounts}`)}
                        </AlertDescription>
                    </Alert>

                    {integration.paused_at ? (
                        <Alert variant="destructive">
                            <CirclePause aria-hidden />
                            <AlertTitle>
                                {t('integrations.paused.title')}
                            </AlertTitle>
                            <AlertDescription>
                                {t('integrations.paused.body', {
                                    time: new Intl.DateTimeFormat(
                                        i18n.language,
                                        {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                        },
                                    ).format(new Date(integration.paused_at)),
                                })}
                            </AlertDescription>
                        </Alert>
                    ) : null}
                </div>

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

                <ActivityList activity={activity} />
            </div>
        </>
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

IntegrationShow.layout = {
    breadcrumbs: [
        {
            get title() {
                return i18n.t('integrations.title');
            },
            href: index(),
        },
    ],
};
