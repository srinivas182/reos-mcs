import { Head, router } from '@inertiajs/react';
import type { RequestPayload } from '@inertiajs/core';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { nativeSelect } from '@/lib/tenants';
import { update } from '@/routes/notification-settings';

type Channel = 'inbox' | 'push' | 'email';

export type NotificationKindName =
    | 'new_device'
    | 'account_deletion'
    | 'privacy_request'
    | 'purchase_receipt'
    | 'payment_failed'
    | 'membership_link'
    | 'seats_requested'
    | 'broker_asked'
    | 'support_access'
    | 'integration_paused'
    | 'flag_rolled_back'
    | 'messages_reported'
    | 'state_rules_changed'
    | 'state_went_live'
    | 'connection_reconnect'
    | 'message'
    | 'announcement'
    | 'session_starting';

type KindRow = {
    kind: NotificationKindName;
    group: (typeof GROUPS)[number];
    locked: boolean;
    urgent: boolean;
    digestible: boolean;
    channels: Record<Channel, boolean>;
    digest: 'daily' | 'weekly' | null;
};

const GROUPS = [
    'messages',
    'learning',
    'organisation',
    'tools',
    'billing',
    'account',
] as const;

/**
 * Settings › Notifications (B6.2, settings screen 23 for every role): each
 * kind by channel (security messages can't be turned off), daily or weekly
 * digests, and quiet hours in your time zone (default 9 PM to 7 AM) that
 * hold back anything not urgent until the morning.
 */
export default function NotificationSettings({
    kinds,
    channels,
    quiet,
    timezone,
    browserTimezone,
    phones = 0,
}: {
    kinds: KindRow[];
    channels: Channel[];
    quiet: { enabled: boolean; start: string; end: string };
    timezone: string;
    browserTimezone: string;
    /** Phones signed in with push on (B6.2b). */
    phones?: number;
}) {
    const { t } = useTranslation();
    const [hours, setHours] = useState(quiet);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const save = (data: RequestPayload) =>
        router.put(update().url, data, {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: setErrors,
            onSuccess: () => setErrors({}),
        });

    return (
        <>
            <Head title={t('notifications.settingsTitle')} />
            <h1 className="sr-only">{t('notifications.settingsTitle')}</h1>

            <div className="space-y-10">
                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('notifications.quiet.title')}
                        description={t('notifications.quiet.description')}
                    />
                    <div className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                        <div className="flex items-center justify-between gap-4">
                            <span id="quiet-label" className="font-medium">
                                {t('notifications.quiet.on')}
                            </span>
                            <Switch
                                checked={hours.enabled}
                                aria-labelledby="quiet-label"
                                onCheckedChange={(on) =>
                                    setHours({ ...hours, enabled: on })
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="quiet-start">
                                    {t('notifications.quiet.start')}
                                </Label>
                                <Input
                                    id="quiet-start"
                                    type="time"
                                    value={hours.start}
                                    disabled={!hours.enabled}
                                    onChange={(e) =>
                                        setHours({
                                            ...hours,
                                            start: e.target.value,
                                        })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="quiet-end">
                                    {t('notifications.quiet.end')}
                                </Label>
                                <Input
                                    id="quiet-end"
                                    type="time"
                                    value={hours.end}
                                    disabled={!hours.enabled}
                                    onChange={(e) =>
                                        setHours({
                                            ...hours,
                                            end: e.target.value,
                                        })
                                    }
                                />
                                <InputError message={errors['quiet.end']} />
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('notifications.quiet.zone', { zone: timezone })}
                            {browserTimezone !== timezone ? (
                                <>
                                    {' '}
                                    <button
                                        type="button"
                                        className="min-h-12 underline underline-offset-4"
                                        onClick={() =>
                                            save({ timezone: browserTimezone })
                                        }
                                    >
                                        {t('notifications.quiet.useZone', {
                                            zone: browserTimezone,
                                        })}
                                    </button>
                                </>
                            ) : null}
                        </p>
                        <Button
                            disabled={busy}
                            onClick={() => save({ quiet: hours })}
                        >
                            {t('notifications.quiet.save')}
                        </Button>
                    </div>
                </section>

                <section className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('notifications.kinds.title')}
                        description={t('notifications.kinds.description')}
                    />
                    <p className="text-base text-muted-foreground">
                        {phones === 0
                            ? t('notifications.phones.none')
                            : t('notifications.phones.some', { count: phones })}
                    </p>
                    <InputError message={errors.channels ?? errors.digests} />
                    {GROUPS.map((group) => {
                        const rows = kinds.filter((k) => k.group === group);

                        if (rows.length === 0) {
                            return null;
                        }

                        return (
                            <div
                                key={group}
                                className="overflow-x-auto rounded-reos-lg border border-border bg-card"
                            >
                                <table className="w-full min-w-[34rem] table-fixed text-left text-base">
                                    <caption className="px-5 pt-4 text-left font-semibold">
                                        {t(`notifications.groups.${group}`)}
                                    </caption>
                                    <colgroup>
                                        <col />
                                        {channels.map((c) => (
                                            <col key={c} className="w-20" />
                                        ))}
                                        <col className="w-48" />
                                    </colgroup>
                                    <thead className="text-sm text-muted-foreground">
                                        <tr className="border-b border-border">
                                            <th
                                                scope="col"
                                                className="px-5 py-3 font-medium"
                                            >
                                                {t('notifications.kinds.kind')}
                                            </th>
                                            {channels.map((c) => (
                                                <th
                                                    key={c}
                                                    scope="col"
                                                    className="px-3 py-3 font-medium"
                                                >
                                                    {t(
                                                        `notifications.channels.${c}`,
                                                    )}
                                                </th>
                                            ))}
                                            <th
                                                scope="col"
                                                className="px-3 py-3 font-medium"
                                            >
                                                {t(
                                                    'notifications.kinds.digest',
                                                )}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {rows.map((row) => {
                                            const name = t(
                                                `notifications.names.${row.kind}`,
                                            );

                                            return (
                                                <tr
                                                    key={row.kind}
                                                    className="border-b border-border last:border-b-0"
                                                >
                                                    <th
                                                        scope="row"
                                                        className="px-5 py-3 font-normal"
                                                    >
                                                        <span className="flex items-center gap-2">
                                                            {name}
                                                            {row.locked ? (
                                                                <Lock
                                                                    aria-label={t(
                                                                        'notifications.kinds.locked',
                                                                    )}
                                                                    className="size-4 text-muted-foreground"
                                                                />
                                                            ) : null}
                                                        </span>
                                                        {row.urgent ? (
                                                            <span className="block text-sm text-muted-foreground">
                                                                {t(
                                                                    'notifications.kinds.urgent',
                                                                )}
                                                            </span>
                                                        ) : null}
                                                    </th>
                                                    {channels.map((c) => (
                                                        <td
                                                            key={c}
                                                            className="px-3 py-1"
                                                        >
                                                            <Switch
                                                                checked={
                                                                    row
                                                                        .channels[
                                                                        c
                                                                    ]
                                                                }
                                                                disabled={
                                                                    row.locked ||
                                                                    busy
                                                                }
                                                                aria-label={t(
                                                                    'notifications.kinds.switch',
                                                                    {
                                                                        kind: name,
                                                                        channel:
                                                                            t(
                                                                                `notifications.channels.${c}`,
                                                                            ),
                                                                    },
                                                                )}
                                                                onCheckedChange={(
                                                                    on,
                                                                ) =>
                                                                    save({
                                                                        channels:
                                                                            {
                                                                                [row.kind]:
                                                                                    {
                                                                                        [c]: on,
                                                                                    },
                                                                            },
                                                                    })
                                                                }
                                                            />
                                                        </td>
                                                    ))}
                                                    <td className="px-3 py-1">
                                                        {row.digestible ? (
                                                            <select
                                                                className={
                                                                    nativeSelect
                                                                }
                                                                value={
                                                                    row.digest ??
                                                                    ''
                                                                }
                                                                aria-label={t(
                                                                    'notifications.kinds.digestFor',
                                                                    {
                                                                        kind: name,
                                                                    },
                                                                )}
                                                                disabled={busy}
                                                                onChange={(e) =>
                                                                    save({
                                                                        digests:
                                                                            {
                                                                                [row.kind]:
                                                                                    e
                                                                                        .target
                                                                                        .value ||
                                                                                    null,
                                                                            },
                                                                    })
                                                                }
                                                            >
                                                                <option value="">
                                                                    {t(
                                                                        'notifications.digests.off',
                                                                    )}
                                                                </option>
                                                                <option value="daily">
                                                                    {t(
                                                                        'notifications.digests.daily',
                                                                    )}
                                                                </option>
                                                                <option value="weekly">
                                                                    {t(
                                                                        'notifications.digests.weekly',
                                                                    )}
                                                                </option>
                                                            </select>
                                                        ) : (
                                                            <span className="text-sm text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        );
                    })}
                </section>
            </div>
        </>
    );
}
