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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { timeAgo } from '@/lib/time-ago';
import { destroy, sync, update } from '@/routes/connections';
import type { ToolConnection } from '@/types';

type History = ToolConnection['history'];
type Calendar = NonNullable<ToolConnection['calendars']>[number];
type Mode = Calendar['mode'];

const HISTORY: History[] = ['3m', '12m', 'all'];
const MODES: Mode[] = ['full', 'busy', 'off'];

/** Radix Select needs a value: this one means "keep REOS events in REOS". */
const NOWHERE = 'none';

/**
 * Manage a connected tool on the website (screen 19's "Manage"): which
 * account, when it last synced and how much, Sync now, how far back, and
 * Disconnect, which asks first because the copied data is removed.
 *
 * Calendars (B4.1) also choose which calendars REOS reads (read and write,
 * busy only, or off) and the calendar REOS's own events go to, as on the
 * phone's "Calendar: what is synced" (Connections and Sync journey, 16).
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
    const [modes, setModes] = useState<Record<string, Mode>>({});
    const [write, setWrite] = useState<string | null>(null);
    const [confirming, setConfirming] = useState(false);

    if (connection === null) {
        return null;
    }

    const tool = connection.integration_name;
    const records = connection.streams.reduce((sum, s) => sum + s.records, 0);
    const chosen = history ?? connection.history;
    const calendars = connection.calendars ?? null;
    const modeOf = (calendar: Calendar) => modes[calendar.id] ?? calendar.mode;
    const savedWrite = calendars?.find((c) => c.write)?.id ?? NOWHERE;
    const writeChoices = (calendars ?? []).filter(
        (c) => c.writable && modeOf(c) === 'full',
    );
    const wanted = write ?? savedWrite;
    const writeTo = writeChoices.some((c) => c.id === wanted)
        ? wanted
        : NOWHERE;
    const calendarsChanged =
        Object.keys(modes).length > 0 || writeTo !== savedWrite;
    const changed = chosen !== connection.history || calendarsChanged;
    const close = () => {
        setHistory(null);
        setModes({});
        setWrite(null);
        setConfirming(false);
        onClose();
    };
    const save = () =>
        router.patch(
            update(connection.id).url,
            {
                ...(chosen !== connection.history ? { history: chosen } : {}),
                ...(calendarsChanged
                    ? {
                          calendars: modes,
                          write_calendar: writeTo === NOWHERE ? '' : writeTo,
                      }
                    : {}),
            },
            { preserveScroll: true, onSuccess: close },
        );

    return (
        <Dialog open onOpenChange={(open) => (open ? null : close())}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
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

                        {calendars ? (
                            <section
                                aria-labelledby="manage-calendars"
                                className="space-y-4"
                            >
                                <div className="space-y-1">
                                    <h3
                                        id="manage-calendars"
                                        className="font-semibold"
                                    >
                                        {t('connections.calendars.title')}
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {t('connections.calendars.intro')}
                                    </p>
                                </div>
                                {calendars.map((calendar) => (
                                    <div
                                        key={calendar.id}
                                        className="space-y-2"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {calendar.name}
                                            </p>
                                            {calendar.primary ||
                                            !calendar.writable ? (
                                                <p className="text-sm text-muted-foreground">
                                                    {calendar.primary
                                                        ? t(
                                                              'connections.calendars.main',
                                                          )
                                                        : t(
                                                              'connections.calendars.readOnly',
                                                          )}
                                                </p>
                                            ) : null}
                                        </div>
                                        <PillGroup<Mode>
                                            label={t(
                                                'connections.calendars.modeFor',
                                                { calendar: calendar.name },
                                            )}
                                            value={modeOf(calendar)}
                                            options={MODES.map((value) => ({
                                                value,
                                                label: t(
                                                    value === 'full' &&
                                                        !calendar.writable
                                                        ? 'connections.calendars.modes.read'
                                                        : `connections.calendars.modes.${value}`,
                                                ),
                                            }))}
                                            onChange={(value) =>
                                                setModes((current) => ({
                                                    ...current,
                                                    [calendar.id]: value,
                                                }))
                                            }
                                        />
                                    </div>
                                ))}
                                <div className="space-y-2">
                                    <Label htmlFor="manage-write-calendar">
                                        {t('connections.calendars.writeTo')}
                                    </Label>
                                    <Select
                                        value={writeTo}
                                        onValueChange={setWrite}
                                    >
                                        <SelectTrigger
                                            id="manage-write-calendar"
                                            className="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {writeChoices.map((c) => (
                                                <SelectItem
                                                    key={c.id}
                                                    value={c.id}
                                                >
                                                    {c.name}
                                                </SelectItem>
                                            ))}
                                            <SelectItem value={NOWHERE}>
                                                {t(
                                                    'connections.calendars.writeNone',
                                                )}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p className="text-sm text-muted-foreground">
                                        {t('connections.calendars.writeHint')}
                                    </p>
                                </div>
                            </section>
                        ) : null}

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
                                <Button disabled={!changed} onClick={save}>
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
