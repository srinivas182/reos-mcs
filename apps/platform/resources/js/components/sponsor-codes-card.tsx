import { Form, router } from '@inertiajs/react';
import { Ticket } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { destroy, store } from '@/routes/tenants/codes';

export type SponsorCodeRow = {
    id: number;
    code: string;
    product: string;
    state: string;
    seats: number;
    used: number;
    access_months: number | null;
    expires_at: string | null;
    active: boolean;
    people: string[];
};

/**
 * Sponsor codes on a tenant's Plan tab (B5.1b; screen 60): codes the tenant
 * pays for, how many seats are used and by whom, Create code and Stop.
 */
export function SponsorCodesCard({
    tenantId,
    codes,
    products,
    states,
}: {
    tenantId: number;
    codes: SponsorCodeRow[];
    products: { id: number; name: string }[];
    states: { code: string; name: string }[];
}) {
    const { t, i18n } = useTranslation();

    return (
        <section
            className="max-w-3xl space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="codes-title"
        >
            <div className="space-y-1">
                <h2 id="codes-title" className="text-title font-semibold">
                    {t('billing.codes.title')}
                </h2>
                <p className="text-body text-muted-foreground">
                    {t('billing.codes.intro')}
                </p>
            </div>

            {codes.length === 0 ? (
                <EmptyState
                    icon={Ticket}
                    title={t('billing.codes.empty')}
                    description={t('billing.codes.intro')}
                />
            ) : (
                <ul className="divide-y divide-border rounded-reos-md border border-border">
                    {codes.map((code) => (
                        <li
                            key={code.id}
                            className="flex flex-wrap items-start justify-between gap-3 px-4 py-3"
                        >
                            <div className="space-y-1">
                                <p className="font-mono text-lg font-semibold">
                                    {code.code}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {[
                                        code.product,
                                        code.state,
                                        t('billing.codes.used', {
                                            used: code.used,
                                            seats: code.seats,
                                        }),
                                        code.expires_at
                                            ? new Date(
                                                  code.expires_at,
                                              ).toLocaleDateString(
                                                  i18n.language,
                                                  { dateStyle: 'medium' },
                                              )
                                            : null,
                                        code.active
                                            ? null
                                            : t('billing.codes.stopped'),
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                {code.people.length > 0 ? (
                                    <p className="text-sm">
                                        {t('billing.codes.people', {
                                            names: code.people.join(', '),
                                        })}
                                    </p>
                                ) : null}
                            </div>
                            {code.active ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.delete(
                                            destroy({
                                                tenant: tenantId,
                                                code: code.id,
                                            }).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('billing.codes.stop', {
                                        code: code.code,
                                    })}
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            <Form
                {...store.form(tenantId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-4 border-t border-border pt-4 sm:grid-cols-2"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="code-product">
                                {t('billing.codes.product')}
                            </Label>
                            <select
                                id="code-product"
                                name="product_id"
                                className={nativeSelect}
                                required
                                defaultValue=""
                            >
                                <option value="">{t('content.choose')}</option>
                                {products.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.product_id} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code-state">
                                {t('billing.codes.state')}
                            </Label>
                            <select
                                id="code-state"
                                name="state"
                                className={nativeSelect}
                                defaultValue="TX"
                            >
                                {states.map((s) => (
                                    <option key={s.code} value={s.code}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code-seats">
                                {t('billing.codes.seats')}
                            </Label>
                            <Input
                                id="code-seats"
                                name="seats"
                                type="number"
                                min={1}
                                max={10000}
                                required
                            />
                            <InputError message={errors.seats} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code-months">
                                {t('billing.codes.months')}
                            </Label>
                            <Input
                                id="code-months"
                                name="access_months"
                                type="number"
                                min={1}
                                max={60}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="code-expires">
                                {t('billing.codes.expires')}
                            </Label>
                            <Input
                                id="code-expires"
                                name="expires_on"
                                type="date"
                            />
                            <InputError message={errors.expires_on} />
                        </div>
                        <Button
                            type="submit"
                            disabled={processing}
                            className="sm:col-span-2 sm:justify-self-start"
                        >
                            {t('billing.codes.create')}
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
