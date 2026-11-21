import { Head, router, usePage } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BrandPreview } from '@/components/brand-preview';
import { BrandTile } from '@/components/brand-tile';
import { CustomDomainCard } from '@/components/custom-domain-card';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    contrastWithWhite,
    MIN_CONTRAST,
    normaliseHex,
    readableShade,
} from '@/lib/colour';
import { logo as logoRoute, update } from '@/routes/branding';
import { destroy as removeLogoRoute } from '@/routes/branding/logo';
import type { BrandProps, TenantDomain, TenantType } from '@/types';

type Props = {
    tenant: {
        id: number;
        name: string;
        type: TenantType;
        address: string | null;
        parent: string | null;
    };
    brand: BrandProps & {
        email_from_name: string | null;
        reply_to: string | null;
    };
    inherited: BrandProps | null;
    domain: TenantDomain | null;
};

type Draft = {
    primary: string;
    app_label: string;
    email_from_name: string;
    reply_to: string;
};

const REOS_BLUE = '#2952CC';

/**
 * Branding and domain (association admin, web journey screen 57): the
 * brand members see (logo, name, primary colour, app label), who sees
 * which brand, and the address members sign in at. A preview shows the
 * sign-in page and an email before saving. A4.2b adds the custom domain
 * records and single sign-on status.
 */
export default function Branding({ tenant, brand, inherited, domain }: Props) {
    const { t } = useTranslation();
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const file = useRef<HTMLInputElement>(null);
    const saved: Draft = useMemo(
        () => ({
            primary: brand.primary ?? '',
            app_label: brand.app_label ?? '',
            email_from_name: brand.email_from_name ?? '',
            reply_to: brand.reply_to ?? '',
        }),
        [brand],
    );
    const [draft, setDraft] = useState<Draft>(saved);
    const [saving, setSaving] = useState(false);

    const hex = normaliseHex(draft.primary);
    const ratio = hex ? contrastWithWhite(hex) : null;
    const readable = ratio === null || ratio >= MIN_CONTRAST;
    const suggestion = hex && !readable ? readableShade(hex) : null;
    const changes = Object.fromEntries(
        (Object.keys(draft) as (keyof Draft)[])
            .filter((key) => draft[key] !== saved[key])
            .map((key) => [key, draft[key] === '' ? null : draft[key]]),
    );
    const dirty = Object.keys(changes).length > 0;

    const save = () =>
        router.put(update().url, changes, {
            preserveScroll: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });

    const upload = (picked: File | undefined) => {
        if (picked) {
            router.post(
                logoRoute().url,
                { logo: picked },
                { forceFormData: true, preserveScroll: true },
            );
        }
    };

    const set =
        (key: keyof Draft) => (event: React.ChangeEvent<HTMLInputElement>) =>
            setDraft({ ...draft, [key]: event.target.value });

    return (
        <>
            <Head title={t('brand.page.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('brand.page.title')}
                    description={t('brand.page.purpose')}
                    actions={
                        <Button
                            onClick={save}
                            disabled={!dirty || saving || !readable}
                        >
                            {saving
                                ? t('brand.page.saving')
                                : t('brand.page.save')}
                        </Button>
                    }
                />

                <div className="grid gap-4 xl:grid-cols-2">
                    <section
                        className="space-y-5 rounded-reos-lg border border-border bg-card p-5"
                        aria-labelledby="brand-title"
                    >
                        <h2 id="brand-title" className="font-semibold">
                            {t('brand.page.brand')}
                        </h2>

                        {inherited ? (
                            <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                                <Info
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0"
                                />
                                {t('brand.page.inherited', {
                                    name: inherited.name,
                                })}
                            </p>
                        ) : null}

                        <div className="flex flex-wrap items-center gap-4">
                            <BrandTile
                                name={tenant.name}
                                logoUrl={brand.logo_url}
                                colour={hex ?? REOS_BLUE}
                                className="size-16 text-lg"
                            />
                            <div className="space-y-2">
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        onClick={() => file.current?.click()}
                                    >
                                        {brand.logo_url
                                            ? t('brand.page.replaceLogo')
                                            : t('brand.page.uploadLogo')}
                                    </Button>
                                    {brand.logo_url ? (
                                        <Button
                                            variant="ghost"
                                            onClick={() =>
                                                router.delete(
                                                    removeLogoRoute().url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('brand.page.removeLogo')}
                                        </Button>
                                    ) : null}
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {t('brand.page.logoHint')}
                                </p>
                                <InputError message={errors.logo} />
                                <input
                                    ref={file}
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    className="sr-only"
                                    aria-label={t('brand.page.uploadLogo')}
                                    tabIndex={-1}
                                    onChange={(e) =>
                                        upload(e.target.files?.[0])
                                    }
                                />
                            </div>
                        </div>

                        <dl className="divide-y divide-border">
                            <Row label={t('brand.page.name')}>
                                {tenant.name}
                            </Row>
                        </dl>

                        <div className="grid gap-2">
                            <Label htmlFor="brand-primary">
                                {t('brand.page.primary')}
                            </Label>
                            <div className="flex items-center gap-3">
                                <input
                                    type="color"
                                    aria-label={t('brand.page.primary')}
                                    value={hex ?? REOS_BLUE}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            primary:
                                                e.target.value.toUpperCase(),
                                        })
                                    }
                                    className="h-12 w-14 shrink-0 cursor-pointer rounded-reos-md border border-input bg-background p-1"
                                />
                                <Input
                                    id="brand-primary"
                                    value={draft.primary}
                                    placeholder={REOS_BLUE}
                                    maxLength={7}
                                    onChange={set('primary')}
                                    aria-describedby="brand-primary-hint brand-primary-check"
                                    aria-invalid={
                                        !readable || Boolean(errors.primary)
                                    }
                                    className="max-w-40 font-mono"
                                />
                            </div>
                            <p
                                id="brand-primary-hint"
                                className="text-sm text-muted-foreground"
                            >
                                {t('brand.page.primaryHint')}
                            </p>
                            {ratio !== null ? (
                                <p
                                    id="brand-primary-check"
                                    role="status"
                                    className={
                                        readable
                                            ? 'text-sm text-reos-tone-success-fg'
                                            : 'text-sm text-reos-tone-danger-fg'
                                    }
                                >
                                    {readable
                                        ? t('brand.page.contrastOk', {
                                              ratio: ratio.toFixed(1),
                                          })
                                        : t('brand.page.contrastLow', {
                                              ratio: ratio.toFixed(1),
                                              suggestion,
                                          })}
                                </p>
                            ) : null}
                            {suggestion ? (
                                <Button
                                    variant="outline"
                                    className="w-fit"
                                    onClick={() =>
                                        setDraft({
                                            ...draft,
                                            primary: suggestion,
                                        })
                                    }
                                >
                                    {t('brand.page.useSuggestion', {
                                        suggestion,
                                    })}
                                </Button>
                            ) : null}
                            <InputError message={errors.primary} />
                        </div>

                        <Field
                            id="brand-app-label"
                            label={t('brand.page.appLabel')}
                            hint={t('brand.page.appLabelHint')}
                            error={errors.app_label}
                        >
                            <Input
                                id="brand-app-label"
                                value={draft.app_label}
                                maxLength={60}
                                onChange={set('app_label')}
                                aria-describedby="brand-app-label-hint"
                            />
                        </Field>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field
                                id="brand-from"
                                label={t('brand.page.emailFrom')}
                                hint={t('brand.page.emailFromHint')}
                                error={errors.email_from_name}
                            >
                                <Input
                                    id="brand-from"
                                    value={draft.email_from_name}
                                    maxLength={80}
                                    onChange={set('email_from_name')}
                                    aria-describedby="brand-from-hint"
                                />
                            </Field>
                            <Field
                                id="brand-reply"
                                label={t('brand.page.replyTo')}
                                error={errors.reply_to}
                            >
                                <Input
                                    id="brand-reply"
                                    type="email"
                                    value={draft.reply_to}
                                    maxLength={254}
                                    onChange={set('reply_to')}
                                />
                            </Field>
                        </div>

                        <dl className="divide-y divide-border border-t border-border">
                            <Row label={t('brand.page.membersSee')}>
                                {t(`brand.page.seen.${tenant.type}`)}
                            </Row>
                            <Row label={t('brand.page.coBranding')}>
                                {t('brand.page.rule')}
                            </Row>
                        </dl>
                    </section>

                    <div className="space-y-4">
                        <CustomDomainCard
                            domain={domain}
                            address={tenant.address ?? ''}
                        />

                        <BrandPreview
                            name={tenant.name}
                            type={tenant.type}
                            logoUrl={brand.logo_url}
                            colour={hex && readable ? hex : REOS_BLUE}
                            address={
                                domain?.live ? domain.hostname : tenant.address
                            }
                            sender={draft.email_from_name || tenant.name}
                        />
                    </div>
                </div>
            </div>
        </>
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
        <div className="flex min-h-11 flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="font-semibold">{children}</dd>
        </div>
    );
}

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint ? (
                <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            <InputError message={error} />
        </div>
    );
}
