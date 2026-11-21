import { Form, router } from '@inertiajs/react';
import { CheckCircle2, Copy, FlaskConical, XCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { disable, enable, test, testProvider, update } from '@/routes/sso';
import type { SsoProtocol, SsoScreen } from '@/types';

const chip: Record<'off' | 'draft' | 'tested' | 'on', string> = {
    off: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    draft: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    tested: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    on: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
};

/**
 * Single sign-on (screen 57, A4.2c): connect the tenant's SAML 2.0 or
 * OpenID Connect identity provider, give it REOS's values, run a test
 * sign-in, then turn it on for members. Saving new settings turns it off
 * until a new test passes, so members only ever use what was tested.
 */
export function SsoCard({ sso, tenant }: { sso: SsoScreen; tenant: string }) {
    const { t, i18n } = useTranslation();
    const settings = sso.settings;
    const [protocol, setProtocol] = useState<SsoProtocol>(
        settings?.protocol ?? 'saml',
    );
    const [busy, setBusy] = useState(false);
    const status = settings?.status ?? 'off';
    const sameProtocol = settings?.protocol === protocol;
    const post = (url: string, data: Record<string, string> = {}) =>
        router.post(url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
        });

    const values =
        protocol === 'saml'
            ? ([
                  ['entityId', sso.service_provider.entity_id],
                  ['acsUrl', sso.service_provider.acs_url],
                  ['spMetadata', sso.service_provider.metadata_url],
              ] as const)
            : ([['redirectUri', sso.service_provider.redirect_uri]] as const);

    return (
        <section
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="sso-title"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="sso-title" className="font-semibold">
                    {t('sso.card.title')}
                </h2>
                <span
                    className={cn(
                        'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                        chip[status],
                    )}
                >
                    {t(`sso.card.status.${status}`)}
                </span>
            </div>
            <p className="text-muted-foreground">
                {t('sso.card.purpose', { name: tenant })}
            </p>

            {settings?.last_test ? (
                <p
                    role="status"
                    className={cn(
                        'flex gap-2 rounded-reos-md p-3 text-sm',
                        settings.last_test.passed
                            ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                            : 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
                    )}
                >
                    {settings.last_test.passed ? (
                        <CheckCircle2 aria-hidden className="size-4 shrink-0" />
                    ) : (
                        <XCircle aria-hidden className="size-4 shrink-0" />
                    )}
                    {t(
                        settings.last_test.passed
                            ? 'sso.card.lastTestPassed'
                            : 'sso.card.lastTestFailed',
                        {
                            when: timeAgo(settings.last_test.at, i18n.language),
                            detail: settings.last_test.detail ?? '',
                        },
                    )}
                </p>
            ) : null}

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="space-y-4"
                resetOnSuccess={['client_secret', 'metadata_file']}
            >
                {({ errors, processing }) => (
                    <>
                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">
                                {t('sso.card.protocol')}
                            </legend>
                            <div className="grid grid-cols-2 gap-2">
                                {(['saml', 'oidc'] as const).map((value) => (
                                    <label
                                        key={value}
                                        className={cn(
                                            'flex min-h-12 cursor-pointer items-center justify-center gap-2 rounded-reos-md border px-3 font-medium',
                                            protocol === value
                                                ? 'border-reos-action bg-reos-action-tint'
                                                : 'border-border',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="protocol"
                                            value={value}
                                            checked={protocol === value}
                                            onChange={() => setProtocol(value)}
                                            className="size-4 accent-reos-action"
                                        />
                                        {t(`sso.card.protocols.${value}`)}
                                    </label>
                                ))}
                            </div>
                        </fieldset>

                        {protocol === 'saml' ? (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="sso-metadata-url">
                                        {t('sso.card.metadataUrl')}
                                    </Label>
                                    <Input
                                        id="sso-metadata-url"
                                        name="metadata_url"
                                        type="url"
                                        inputMode="url"
                                        spellCheck={false}
                                        defaultValue={
                                            sameProtocol
                                                ? (settings?.metadata_url ?? '')
                                                : ''
                                        }
                                        aria-describedby="sso-metadata-hint"
                                        aria-invalid={Boolean(
                                            errors.metadata_url,
                                        )}
                                    />
                                    <p
                                        id="sso-metadata-hint"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {sameProtocol
                                            ? t('sso.card.metadataSaved')
                                            : t('sso.card.metadataUrlHint')}
                                    </p>
                                    <InputError message={errors.metadata_url} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="sso-metadata-file">
                                        {t('sso.card.metadataFile')}
                                    </Label>
                                    <Input
                                        id="sso-metadata-file"
                                        name="metadata_file"
                                        type="file"
                                        accept=".xml,application/xml,text/xml"
                                        aria-invalid={Boolean(
                                            errors.metadata_file ??
                                            errors.metadata_xml,
                                        )}
                                    />
                                    <InputError
                                        message={
                                            errors.metadata_file ??
                                            errors.metadata_xml
                                        }
                                    />
                                </div>
                                {sameProtocol && settings?.entity_id ? (
                                    <div className="space-y-2">
                                        <dl className="divide-y divide-border rounded-reos-md border border-border px-3 text-sm">
                                            <Row label={t('sso.card.provider')}>
                                                {settings.entity_id}
                                            </Row>
                                            <Row
                                                label={t(
                                                    'sso.card.signInAddress',
                                                )}
                                            >
                                                {settings.sso_url}
                                            </Row>
                                        </dl>
                                        {settings.certificate_expires ? (
                                            <p className="text-sm text-muted-foreground">
                                                {t(
                                                    'sso.card.certificateExpires',
                                                    {
                                                        date: new Date(
                                                            settings.certificate_expires,
                                                        ).toLocaleDateString(
                                                            i18n.language,
                                                            {
                                                                dateStyle:
                                                                    'medium',
                                                            },
                                                        ),
                                                    },
                                                )}
                                            </p>
                                        ) : null}
                                    </div>
                                ) : null}
                            </>
                        ) : (
                            <>
                                <Field
                                    id="sso-issuer"
                                    name="issuer"
                                    type="url"
                                    label={t('sso.card.issuer')}
                                    defaultValue={
                                        sameProtocol
                                            ? (settings?.issuer ?? '')
                                            : ''
                                    }
                                    error={errors.issuer}
                                />
                                <Field
                                    id="sso-client-id"
                                    name="client_id"
                                    label={t('sso.card.clientId')}
                                    defaultValue={
                                        sameProtocol
                                            ? (settings?.client_id ?? '')
                                            : ''
                                    }
                                    error={errors.client_id}
                                />
                                <Field
                                    id="sso-client-secret"
                                    name="client_secret"
                                    type="password"
                                    autoComplete="new-password"
                                    label={t('sso.card.clientSecret')}
                                    hint={
                                        sameProtocol && settings?.has_secret
                                            ? t('sso.card.secretSaved')
                                            : undefined
                                    }
                                    error={errors.client_secret}
                                />
                            </>
                        )}

                        {settings ? (
                            <p className="text-sm text-muted-foreground">
                                {t('sso.card.changeNote')}
                            </p>
                        ) : null}
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing
                                ? t('sso.card.saving')
                                : t('sso.card.save')}
                        </Button>
                    </>
                )}
            </Form>

            <div className="space-y-2">
                <h3 className="text-sm font-semibold">
                    {t('sso.card.giveProvider')}
                </h3>
                <dl className="divide-y divide-border rounded-reos-md border border-border px-3 text-sm">
                    {values.map(([key, value]) => (
                        <CopyRow
                            key={key}
                            label={t(`sso.card.${key}`)}
                            value={value}
                        />
                    ))}
                </dl>
            </div>

            {settings?.test_provider ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                    <FlaskConical aria-hidden className="size-4 shrink-0" />
                    {t('sso.card.testProviderInUse')}
                </p>
            ) : null}

            <div className="flex flex-wrap gap-2">
                {settings ? (
                    <Button
                        variant={status === 'draft' ? 'default' : 'outline'}
                        disabled={busy}
                        onClick={() => post(test().url)}
                    >
                        {t('sso.card.test')}
                    </Button>
                ) : null}
                {status === 'tested' ? (
                    <Button disabled={busy} onClick={() => post(enable().url)}>
                        {t('sso.card.enable')}
                    </Button>
                ) : null}
                {status === 'on' ? (
                    <Button
                        variant="outline"
                        disabled={busy}
                        onClick={() => post(disable().url)}
                    >
                        {t('sso.card.disable')}
                    </Button>
                ) : null}
            </div>

            {sso.test_provider_available && !settings?.test_provider ? (
                <div className="space-y-2 rounded-reos-md border border-dashed border-border p-3">
                    <Button
                        variant="ghost"
                        disabled={busy}
                        onClick={() => post(testProvider().url, { protocol })}
                    >
                        <FlaskConical aria-hidden className="size-4" />
                        {t('sso.card.testProvider')}
                    </Button>
                    <p className="text-sm text-muted-foreground">
                        {t('sso.card.testProviderHint')}
                    </p>
                </div>
            ) : null}

            <p className="text-sm text-muted-foreground">
                {t('sso.card.linking', { name: tenant })}
            </p>
            {sso.members > 0 ? (
                <p className="text-sm text-muted-foreground">
                    {t('sso.card.members', { count: sso.members })}
                </p>
            ) : null}
        </section>
    );
}

/** The status line under the custom domain, as on journey screen 57. */
export function SsoStatusNote({
    sso,
    tenant,
}: {
    sso: SsoScreen;
    tenant: string;
}) {
    const { t } = useTranslation();
    const on = sso.settings?.status === 'on';

    return (
        <p
            className={cn(
                'flex gap-2 rounded-reos-md p-3 text-sm',
                on
                    ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                    : 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
            )}
        >
            {on ? (
                <CheckCircle2 aria-hidden className="size-4 shrink-0" />
            ) : null}
            {on
                ? t('sso.card.onNote', { name: tenant })
                : t('sso.card.offNote')}
        </p>
    );
}

function Row({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 font-medium break-all">{children}</dd>
        </div>
    );
}

function CopyRow({ label, value }: { label: string; value: string }) {
    const { t } = useTranslation();
    const [copied, setCopied] = useState(false);

    return (
        <div className="py-1">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="flex items-center gap-2">
                <span
                    className="min-w-0 flex-1 truncate font-mono"
                    title={value}
                >
                    {value}
                </span>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={
                        copied
                            ? t('sso.card.copied')
                            : t('sso.card.copy', { label })
                    }
                    onClick={() => {
                        void navigator.clipboard?.writeText(value);
                        setCopied(true);
                        window.setTimeout(() => setCopied(false), 2000);
                    }}
                >
                    {copied ? (
                        <CheckCircle2 aria-hidden className="size-4" />
                    ) : (
                        <Copy aria-hidden className="size-4" />
                    )}
                </Button>
            </dd>
        </div>
    );
}

function Field({
    id,
    name,
    label,
    hint,
    error,
    type = 'text',
    defaultValue = '',
    autoComplete = 'off',
}: {
    id: string;
    name: string;
    label: string;
    hint?: string;
    error?: string;
    type?: string;
    defaultValue?: string;
    autoComplete?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type={type}
                defaultValue={defaultValue}
                autoComplete={autoComplete}
                spellCheck={false}
                aria-describedby={hint ? `${id}-hint` : undefined}
                aria-invalid={Boolean(error)}
            />
            {hint ? (
                <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
