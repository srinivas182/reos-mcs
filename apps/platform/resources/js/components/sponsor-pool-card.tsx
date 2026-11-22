import { Form } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fund } from '@/routes/tenants/pool';

export type SponsorPools = { credits: number; seats: number; funded: boolean };

/**
 * A tenant's sponsor pools on its Plan tab (B5.3; screen 60): AI credits
 * its people's AI use draws on (candidates are never charged) and seats
 * its sponsor codes draw on. REOS adds what the tenant paid for, with a
 * note (an invoice or contract), and it's kept in the ledger and the audit log.
 */
export function SponsorPoolCard({
    tenantId,
    pools,
}: {
    tenantId: number;
    pools: SponsorPools;
}) {
    const { t, i18n } = useTranslation();
    const number = new Intl.NumberFormat(i18n.language);

    return (
        <section
            className="max-w-3xl space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="pool-title"
        >
            <div className="space-y-1">
                <h2 id="pool-title" className="text-title font-semibold">
                    {t('billing.pools.title')}
                </h2>
                <p className="text-body text-muted-foreground">
                    {pools.funded
                        ? t('billing.pools.intro')
                        : t('billing.pools.notFunded')}
                </p>
            </div>
            <dl className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-reos-md border border-border p-4">
                    <dt className="text-muted-foreground">
                        {t('billing.pools.credits')}
                    </dt>
                    <dd className="text-title font-semibold tabular-nums">
                        {number.format(pools.credits)}
                    </dd>
                </div>
                <div className="rounded-reos-md border border-border p-4">
                    <dt className="text-muted-foreground">
                        {t('billing.pools.seats')}
                    </dt>
                    <dd className="text-title font-semibold tabular-nums">
                        {number.format(pools.seats)}
                    </dd>
                </div>
            </dl>
            <Form
                {...fund.form(tenantId)}
                options={{ preserveScroll: true }}
                resetOnSuccess
                className="grid gap-3 sm:grid-cols-[1fr_1fr_2fr_auto] sm:items-end"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="pool-credits">
                                {t('billing.pools.addCredits')}
                            </Label>
                            <Input
                                id="pool-credits"
                                name="credits"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pool-seats">
                                {t('billing.pools.addSeats')}
                            </Label>
                            <Input
                                id="pool-seats"
                                name="seats"
                                type="number"
                                min={0}
                                defaultValue={0}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="pool-note">
                                {t('billing.pools.note')}
                            </Label>
                            <Input
                                id="pool-note"
                                name="note"
                                required
                                maxLength={200}
                                placeholder={t('billing.pools.notePlaceholder')}
                            />
                        </div>
                        <Button type="submit" disabled={processing}>
                            {t('billing.pools.add')}
                        </Button>
                        <InputError
                            className="sm:col-span-4"
                            message={
                                errors.credits ?? errors.seats ?? errors.note
                            }
                        />
                    </>
                )}
            </Form>
        </section>
    );
}
