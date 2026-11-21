import { router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PillGroup } from '@/components/pill-group';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { timeAgo } from '@/lib/time-ago';
import { destroy, sync, update } from '@/routes/connections';
import type { ToolConnection } from '@/types';

type History = ToolConnection['history'];

const HISTORY: History[] = ['3m', '12m', 'all'];

/**
 * Manage a connected tool on the website (screen 19's "Manage"): which
 * account, when it last synced and how much, Sync now, how far back, and
 * Disconnect, which asks first because the copied data is removed.
 */
export function ManageConnectionDialog({
    connection,
    onClose,
}: {
    connection: ToolConnection | null;
    onClose: () => void;
}) {
    const { t, i18n } = useTranslation();
    const [history, setHistory] = useState<History | null>(null);
    const [confirming, setConfirming] = useState(false);

    if (connection === null) {
        return null;
    }

    const tool = connection.integration_name;
    const records = connection.streams.reduce((sum, s) => sum + s.records, 0);
    const chosen = history ?? connection.history;
    const close = () => {
        setHistory(null);
        setConfirming(false);
        onClose();
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? null : close())}>
            <DialogContent className="sm:max-w-lg">
                {confirming ? (
                    <>
                        <DialogHeader>
                            <DialogTitle>
                                {t('connections.detail.confirmTitle', { tool })}
                            </DialogTitle>
                            <DialogDescription>
                                {t('connections.detail.confirmBody', { tool })}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2">
                            <Button
                                variant="secondary"
                                onClick={() => setConfirming(false)}
                            >
                                {t('connections.detail.keep')}
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    router.delete(destroy(connection.id).url, {
                                        preserveScroll: true,
                                        onSuccess: close,
                                    })
                                }
                            >
                                {t('connections.detail.confirm')}
                            </Button>
                        </DialogFooter>
                    </>
                ) : (
                    <>
                        <DialogHeader>
                            <DialogTitle>{tool}</DialogTitle>
                            <DialogDescription>
                                {connection.account
                                    ? t('connections.web.manageIntro', {
                                          account: connection.account,
                                      })
                                    : null}
                            </DialogDescription>
                        </DialogHeader>

                        <dl className="grid grid-cols-2 gap-4 rounded-reos-md bg-muted p-4">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    {t('connections.web.lastSync')}
                                </dt>
                                <dd className="font-semibold">
                                    {connection.last_synced_at
                                        ? timeAgo(
                                              connection.last_synced_at,
                                              i18n.language,
                                          )
                                        : t('connections.hub.never')}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    {t('connections.web.records')}
                                </dt>
                                <dd className="font-semibold">
                                    {t('connections.detail.records', {
                                        count: records,
                                    })}
                                </dd>
                            </div>
                        </dl>

                        <PillGroup
                            label={t('connections.settings.howFar')}
                            value={chosen}
                            options={HISTORY.map((value) => ({
                                value,
                                label: t(
                                    `connections.connected.historyValues.${value}`,
                                ),
                            }))}
                            onChange={setHistory}
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('connections.settings.privacy')}
                        </p>

                        <DialogFooter className="flex-wrap gap-2 sm:justify-between">
                            <Button
                                variant="ghost"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={() => setConfirming(true)}
                            >
                                {t('connections.detail.disconnect')}
                            </Button>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            sync(connection.id).url,
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <RefreshCw aria-hidden className="size-5" />
                                    {t('connections.detail.syncNow')}
                                </Button>
                                <Button
                                    disabled={chosen === connection.history}
                                    onClick={() =>
                                        router.patch(
                                            update(connection.id).url,
                                            { history: chosen },
                                            {
                                                preserveScroll: true,
                                                onSuccess: close,
                                            },
                                        )
                                    }
                                >
                                    {t('connections.settings.save')}
                                </Button>
                            </div>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
