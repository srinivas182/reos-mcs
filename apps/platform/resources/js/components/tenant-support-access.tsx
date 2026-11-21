import { router } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { start, store } from '@/routes/tenants/support';
import type { TenantSupport } from '@/types';

/**
 * "Sign in as support (with consent)" on Tenant detail (screen 60, A4.1c).
 * Ask with a reason; once an owner approves, start the 60-minute session.
 */
export function TenantSupportAccess({
    tenant,
    support,
}: {
    tenant: { id: number; name: string };
    support: TenantSupport;
}) {
    const { t } = useTranslation();
    const [asking, setAsking] = useState(false);
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const latest = support.latest;
    const open =
        latest !== null &&
        ['pending', 'approved', 'active'].includes(latest.status);

    return (
        <>
            {latest?.status === 'approved' && latest.mine ? (
                <Button onClick={() => router.post(start(tenant.id).url)}>
                    <LifeBuoy aria-hidden className="size-5" />
                    {t('support.tenant.start')}
                </Button>
            ) : (
                <Button
                    variant="outline"
                    disabled={!support.can_ask || open}
                    title={
                        support.can_ask
                            ? undefined
                            : t('support.tenant.noOwner')
                    }
                    onClick={() => setAsking(true)}
                >
                    {t('support.tenant.ask')}
                </Button>
            )}

            <Dialog open={asking} onOpenChange={setAsking}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('support.tenant.askTitle', {
                                tenant: tenant.name,
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('support.tenant.askBody', {
                                tenant: tenant.name,
                            })}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="support-reason">
                            {t('support.tenant.reason')}
                        </Label>
                        <Textarea
                            id="support-reason"
                            rows={3}
                            maxLength={500}
                            value={reason}
                            aria-invalid={error !== null}
                            aria-describedby={
                                error ? 'support-reason-error' : undefined
                            }
                            onChange={(e) => setReason(e.target.value)}
                        />
                        {error ? (
                            <p
                                id="support-reason-error"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setAsking(false)}
                        >
                            {t('support.tenant.cancel')}
                        </Button>
                        <Button
                            onClick={() =>
                                router.post(
                                    store(tenant.id).url,
                                    { reason },
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => {
                                            setAsking(false);
                                            setReason('');
                                            setError(null);
                                        },
                                        onError: (errors) =>
                                            setError(errors.reason ?? null),
                                    },
                                )
                            }
                        >
                            {t('support.tenant.send')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Where support sign-in stands, under the tenant's header (A4.1c). */
export function TenantSupportStatus({ support }: { support: TenantSupport }) {
    const { t } = useTranslation();
    const latest = support.latest;
    const text = !support.can_ask
        ? t('support.tenant.noOwner')
        : latest
          ? t(`support.tenant.statuses.${latest.status}`, {
                person: latest.decided_by ?? '',
                defaultValue: '',
            })
          : '';

    return text ? (
        <p
            role="status"
            className="flex items-center gap-2 text-sm text-muted-foreground"
        >
            <LifeBuoy aria-hidden className="size-4 shrink-0" />
            {text}
        </p>
    ) : null;
}
