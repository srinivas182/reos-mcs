import { Form, router } from '@inertiajs/react';
import { CheckCircle2, Copy, Globe, Loader2 } from 'lucide-react';
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
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { destroy, store, verify } from '@/routes/domain';
import type { TenantDomain } from '@/types';

/**
 * The record name as DNS providers ask for it: relative to the domain the
 * tenant manages (`learn` for learn.lonestarrealty.com). The full name is
 * kept in the tooltip.
 */
export function dnsName(name: string): string {
    const labels = name.split('.');

    return labels.length > 2 ? labels.slice(0, -2).join('.') : name;
}

/**
 * Custom domain (screens 41 and 57, A4.2b): the tenant's own app address,
 * the DNS records to add with their status, Verify DNS, and remove.
 * SSL is automatic once the records are in place.
 */
export function CustomDomainCard({
    domain,
    address,
    children,
}: {
    domain: TenantDomain | null;
    /** The REOS address that works until (and unless) the domain is live. */
    address: string;
    /** Extra rows under the card (single sign-on status on screen 57). */
    children?: React.ReactNode;
}) {
    const { t, i18n } = useTranslation();
    const [checking, setChecking] = useState(false);
    const [removing, setRemoving] = useState(false);
    const [copied, setCopied] = useState<string | null>(null);

    const copy = (value: string) => {
        void navigator.clipboard?.writeText(value);
        setCopied(value);
        window.setTimeout(() => setCopied(null), 2000);
    };

    return (
        <section
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="domain-title"
        >
            <h2 id="domain-title" className="font-semibold">
                {t('domains.card.title')}
            </h2>

            {domain === null ? (
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    className="space-y-3"
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="domain-hostname">
                                {t('domains.card.yourAddress')}
                            </Label>
                            <div className="flex flex-wrap gap-2">
                                <Input
                                    id="domain-hostname"
                                    name="hostname"
                                    required
                                    placeholder={t('domains.card.placeholder')}
                                    autoComplete="off"
                                    spellCheck={false}
                                    className="min-w-0 flex-1"
                                    aria-invalid={Boolean(errors.hostname)}
                                />
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? t('domains.card.adding')
                                        : t('domains.card.add')}
                                </Button>
                            </div>
                            <InputError message={errors.hostname} />
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Globe
                                    aria-hidden
                                    className="size-4 shrink-0"
                                />
                                {t('domains.card.reosAddress', { address })}
                            </p>
                        </>
                    )}
                </Form>
            ) : (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="domain-current">
                            {t('domains.card.yourAddress')}
                        </Label>
                        <Input
                            id="domain-current"
                            value={domain.hostname}
                            readOnly
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[26rem] text-left text-sm">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'type',
                                            'name',
                                            'value',
                                            'status',
                                        ] as const
                                    ).map((column) => (
                                        <th
                                            key={column}
                                            scope="col"
                                            className="px-2 py-2"
                                        >
                                            {t(
                                                `domains.card.columns.${column}`,
                                            )}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {domain.records.map((record) => (
                                    <tr key={`${record.type}-${record.name}`}>
                                        <td className="px-2 py-2 font-medium">
                                            {record.type}
                                        </td>
                                        <td
                                            className="max-w-36 truncate px-2 py-2 font-mono"
                                            title={record.name}
                                        >
                                            {dnsName(record.name)}
                                        </td>
                                        <td className="px-2 py-2">
                                            <span className="flex items-center gap-1">
                                                <span
                                                    className="max-w-32 truncate font-mono"
                                                    title={record.value}
                                                >
                                                    {record.value}
                                                </span>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={
                                                        copied === record.value
                                                            ? t(
                                                                  'domains.card.copied',
                                                              )
                                                            : t(
                                                                  'domains.card.copy',
                                                                  {
                                                                      type: record.type,
                                                                  },
                                                              )
                                                    }
                                                    onClick={() =>
                                                        copy(record.value)
                                                    }
                                                >
                                                    {copied === record.value ? (
                                                        <CheckCircle2
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                    ) : (
                                                        <Copy
                                                            aria-hidden
                                                            className="size-4"
                                                        />
                                                    )}
                                                </Button>
                                            </span>
                                        </td>
                                        <td className="px-2 py-2">
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                                    record.verified
                                                        ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                        : 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
                                                )}
                                            >
                                                {record.verified
                                                    ? t('domains.card.verified')
                                                    : t('domains.card.waiting')}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <p
                        role="status"
                        className={cn(
                            'flex gap-2 rounded-reos-md p-3 text-sm',
                            domain.live
                                ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                : 'bg-reos-tone-info-bg text-reos-tone-info-fg',
                        )}
                    >
                        <CheckCircle2
                            aria-hidden
                            className="mt-0.5 size-4 shrink-0"
                        />
                        {domain.live
                            ? t('domains.card.liveNote')
                            : (domain.error ?? t('domains.card.pendingNote'))}
                    </p>

                    <div className="flex flex-wrap items-center gap-2">
                        {!domain.live ? (
                            <Button
                                disabled={checking}
                                onClick={() =>
                                    router.post(
                                        verify().url,
                                        {},
                                        {
                                            preserveScroll: true,
                                            onStart: () => setChecking(true),
                                            onFinish: () => setChecking(false),
                                        },
                                    )
                                }
                            >
                                {checking ? (
                                    <Loader2
                                        aria-hidden
                                        className="size-4 animate-spin"
                                    />
                                ) : null}
                                {checking
                                    ? t('domains.card.checking')
                                    : t('domains.card.verify')}
                            </Button>
                        ) : null}
                        <Button
                            variant="ghost"
                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                            onClick={() => setRemoving(true)}
                        >
                            {t('domains.card.remove')}
                        </Button>
                        {domain.last_checked_at ? (
                            <span className="text-sm text-muted-foreground">
                                {t('domains.card.lastChecked', {
                                    when: timeAgo(
                                        domain.last_checked_at,
                                        i18n.language,
                                    ).toLowerCase(),
                                })}
                            </span>
                        ) : null}
                    </div>

                    <Dialog open={removing} onOpenChange={setRemoving}>
                        <DialogContent className="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>
                                    {t('domains.card.removeTitle', {
                                        domain: domain.hostname,
                                    })}
                                </DialogTitle>
                                <DialogDescription>
                                    {t('domains.card.removeBody', {
                                        domain: domain.hostname,
                                        address,
                                    })}
                                </DialogDescription>
                            </DialogHeader>
                            <DialogFooter className="gap-2">
                                <Button
                                    variant="secondary"
                                    onClick={() => setRemoving(false)}
                                >
                                    {t('domains.card.keep')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() =>
                                        router.delete(destroy().url, {
                                            preserveScroll: true,
                                            onSuccess: () => setRemoving(false),
                                        })
                                    }
                                >
                                    {t('domains.card.confirmRemove')}
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                </>
            )}
            {children}
        </section>
    );
}
