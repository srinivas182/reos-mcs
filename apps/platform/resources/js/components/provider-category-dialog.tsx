import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { nativeSelect } from '@/lib/tenants';
import { store, update } from '@/routes/provider-categories';

export type Tier = 'basic' | 'pro' | 'premium';
export type CategoryStatus = 'live' | 'draft' | 'requested' | 'retired';

export type ProviderCategoryRow = {
    id: number;
    key: string;
    name: string;
    description: string | null;
    licence_check: string | null;
    verification: string | null;
    profile_fields: string[];
    special_permissions: string | null;
    areas_on_pro: number;
    status: CategoryStatus;
    requests: number;
    providers: number;
    prices: { state: string; tier: Tier; monthly: number }[];
};

export type CategoryOptions = {
    tiers: Tier[];
    statuses: CategoryStatus[];
    states: { code: string; name: string }[];
};

/**
 * Add category and edit settings (screen 68, A6.1): licence check,
 * verification, profile fields, permissions, areas on Pro, status and the
 * flat monthly price of each plan in a state (RESPA: flat fees only).
 */
export function ProviderCategoryDialog({
    open,
    category,
    options,
    onOpenChange,
}: {
    open: boolean;
    category: ProviderCategoryRow | null;
    options: CategoryOptions;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {category
                            ? t('providerCategories.edit', {
                                  name: category.name,
                              })
                            : t('providerCategories.add')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('providerCategories.respa')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <CategoryForm
                        key={category?.id ?? 'new'}
                        category={category}
                        options={options}
                        onDone={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function CategoryForm({
    category,
    options,
    onDone,
}: {
    category: ProviderCategoryRow | null;
    options: CategoryOptions;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [state, setState] = useState(
        options.states.find((s) => s.code === 'TX')?.code ??
            options.states[0]?.code ??
            'TX',
    );
    const priceOf = (code: string, tier: Tier) => {
        const found = category?.prices.find(
            (p) => p.state === code && p.tier === tier,
        );

        return found ? String(found.monthly / 100) : '';
    };
    const [draft, setDraft] = useState({
        name: category?.name ?? '',
        licence_check: category?.licence_check ?? '',
        verification: category?.verification ?? '',
        profile_fields: (category?.profile_fields ?? []).join('\n'),
        special_permissions: category?.special_permissions ?? '',
        areas_on_pro: String(category?.areas_on_pro ?? 3),
        status: category?.status ?? 'draft',
    });
    const [prices, setPrices] = useState<Record<string, Record<Tier, string>>>(
        () =>
            Object.fromEntries(
                options.states.map((s) => [
                    s.code,
                    Object.fromEntries(
                        options.tiers.map((tier) => [
                            tier,
                            priceOf(s.code, tier),
                        ]),
                    ) as Record<Tier, string>,
                ]),
            ),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        const payload = {
            ...draft,
            areas_on_pro: Number(draft.areas_on_pro),
            prices: Object.entries(prices).flatMap(([code, tiers]) =>
                Object.entries(tiers)
                    .filter(([, value]) => value !== '')
                    .map(([tier, value]) => ({
                        state: code,
                        tier,
                        monthly: value,
                    })),
            ),
        };
        const options = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: onDone,
        };

        if (category) {
            router.patch(update(category.id).url, payload, options);
        } else {
            router.post(store().url, payload, options);
        }
    };

    const field = (key: keyof typeof draft, label: string, max = 200) => (
        <div className="grid gap-2">
            <Label htmlFor={`category-${key}`}>{label}</Label>
            <Input
                id={`category-${key}`}
                value={draft[key]}
                maxLength={max}
                onChange={(e) => setDraft({ ...draft, [key]: e.target.value })}
            />
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            {field('name', t('providerCategories.fields.name'), 120)}
            <div className="grid gap-4 sm:grid-cols-2">
                {field(
                    'licence_check',
                    t('providerCategories.fields.licenceCheck'),
                    120,
                )}
                {field(
                    'verification',
                    t('providerCategories.fields.verification'),
                )}
            </div>
            <div className="grid gap-2">
                <Label htmlFor="category-fields">
                    {t('providerCategories.fields.profileFields')}
                </Label>
                <Textarea
                    id="category-fields"
                    rows={3}
                    value={draft.profile_fields}
                    onChange={(e) =>
                        setDraft({ ...draft, profile_fields: e.target.value })
                    }
                />
            </div>
            {field(
                'special_permissions',
                t('providerCategories.fields.permissions'),
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="category-areas">
                        {t('providerCategories.fields.areasOnPro')}
                    </Label>
                    <Input
                        id="category-areas"
                        type="number"
                        min={1}
                        max={50}
                        value={draft.areas_on_pro}
                        onChange={(e) =>
                            setDraft({ ...draft, areas_on_pro: e.target.value })
                        }
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="category-status">
                        {t('providerCategories.fields.status')}
                    </Label>
                    <select
                        id="category-status"
                        className={nativeSelect}
                        value={draft.status}
                        onChange={(e) =>
                            setDraft({
                                ...draft,
                                status: e.target.value as CategoryStatus,
                            })
                        }
                    >
                        {options.statuses.map((s) => (
                            <option key={s} value={s}>
                                {t(`providerCategories.statuses.${s}`)}
                            </option>
                        ))}
                    </select>
                </div>
            </div>
            <fieldset className="space-y-3 rounded-reos-md border border-border p-3">
                <legend className="px-1 font-medium">
                    {t('providerCategories.fields.prices')}
                </legend>
                <div className="grid gap-2">
                    <Label htmlFor="category-price-state">
                        {t('providerCategories.fields.state')}
                    </Label>
                    <select
                        id="category-price-state"
                        className={nativeSelect}
                        value={state}
                        onChange={(e) => setState(e.target.value)}
                    >
                        {options.states.map((s) => (
                            <option key={s.code} value={s.code}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-3 sm:grid-cols-3">
                    {options.tiers.map((tier) => (
                        <div key={tier} className="grid gap-2">
                            <Label htmlFor={`category-price-${tier}`}>
                                {t(`providerCategories.tiers.${tier}`)}
                            </Label>
                            <Input
                                id={`category-price-${tier}`}
                                type="number"
                                min={0}
                                step={1}
                                value={prices[state]?.[tier] ?? ''}
                                onChange={(e) =>
                                    setPrices({
                                        ...prices,
                                        [state]: {
                                            ...(prices[state] ?? {
                                                basic: '',
                                                pro: '',
                                                premium: '',
                                            }),
                                            [tier]: e.target.value,
                                        },
                                    })
                                }
                            />
                        </div>
                    ))}
                </div>
                <InputError
                    message={errors['prices.0.monthly'] ?? errors.prices}
                />
            </fieldset>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('common.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('providerCategories.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
