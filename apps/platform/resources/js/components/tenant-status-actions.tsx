import { router } from '@inertiajs/react';
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
import { status as statusRoute } from '@/routes/tenants';
import type { TenantStatus } from '@/types';

type Target = 'suspended' | 'archived';

/**
 * Suspend, archive or restore a tenant (screen 60). Suspending and archiving
 * sign its people out, so they ask for a reason, which goes in the audit
 * log; restoring is one click.
 */
export function TenantStatusActions({
    tenant,
}: {
    tenant: { id: number; name: string; status: TenantStatus };
}) {
    const { t } = useTranslation();
    const [target, setTarget] = useState<Target | null>(null);
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);

    const send = (status: TenantStatus, why: string | null) =>
        router.put(
            statusRoute(tenant.id).url,
            { status, reason: why },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTarget(null);
                    setReason('');
                    setError(null);
                },
                onError: (errors) =>
                    setError(errors.reason ?? errors.status ?? null),
            },
        );

    if (tenant.status === 'suspended' || tenant.status === 'archived') {
        return (
            <Button variant="outline" onClick={() => send('active', null)}>
                {t('tenants.status.restore')}
            </Button>
        );
    }

    return (
        <>
            <Button variant="outline" onClick={() => setTarget('suspended')}>
                {t('tenants.status.suspend')}
            </Button>
            <Dialog
                open={target !== null}
                onOpenChange={(open) => (open ? null : setTarget(null))}
            >
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t(
                                target === 'archived'
                                    ? 'tenants.status.archiveTitle'
                                    : 'tenants.status.suspendTitle',
                                {
                                    name: tenant.name,
                                },
                            )}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                target === 'archived'
                                    ? 'tenants.status.archiveBody'
                                    : 'tenants.status.suspendBody',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-2">
                        <Label htmlFor="status-reason">
                            {t('tenants.status.reason')}
                        </Label>
                        <Textarea
                            id="status-reason"
                            value={reason}
                            maxLength={500}
                            rows={3}
                            aria-invalid={error !== null}
                            aria-describedby={
                                error ? 'status-reason-error' : undefined
                            }
                            onChange={(e) => setReason(e.target.value)}
                        />
                        {error ? (
                            <p
                                id="status-reason-error"
                                className="text-sm text-destructive"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                    <DialogFooter className="flex-wrap gap-2 sm:justify-between">
                        {target === 'suspended' ? (
                            <Button
                                variant="ghost"
                                onClick={() => setTarget('archived')}
                            >
                                {t('tenants.status.archive')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => setTarget(null)}
                            >
                                {t('tenants.status.keep')}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    send(target ?? 'suspended', reason)
                                }
                            >
                                {t(
                                    target === 'archived'
                                        ? 'tenants.status.confirmArchive'
                                        : 'tenants.status.confirmSuspend',
                                )}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
