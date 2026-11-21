import { usePage } from '@inertiajs/react';
import { RefreshCcw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { timeAgo } from '@/lib/time-ago';
import { toolInitials } from '@/lib/tool-initials';
import { cn } from '@/lib/utils';
import { connect } from '@/routes/connections';

export type ConnectionAlertItem = {
    id: number;
    integration: string;
    name: string;
    account: string | null;
    since: string | null;
};

/**
 * "When a sync breaks" (Connections and Sync journey, screen 26): what stopped,
 * since when, and a 30-second fix that signs in to the tool again.
 */
export function ConnectionAlert({
    alert,
    className,
}: {
    alert: ConnectionAlertItem;
    className?: string;
}) {
    const { t, i18n } = useTranslation();
    const title = t('connections.alert.title', { tool: alert.name });

    return (
        <section
            aria-label={title}
            className={cn(
                'flex flex-col gap-4 rounded-reos-lg border border-reos-tone-danger-fg/40 bg-reos-tone-danger-bg p-5 sm:flex-row sm:items-center',
                className,
            )}
        >
            <span
                aria-hidden
                className="flex size-12 shrink-0 items-center justify-center rounded-reos-md bg-primary font-bold text-primary-foreground"
            >
                {toolInitials(alert.name)}
            </span>
            <div className="min-w-0 flex-1 space-y-1">
                <h2 className="text-title font-semibold text-reos-tone-danger-fg">
                    {title}
                </h2>
                <p className="text-body text-reos-tone-danger-fg">
                    {t('connections.alert.body', {
                        tool: alert.name,
                        ago: alert.since
                            ? timeAgo(alert.since, i18n.language)
                            : '',
                    }).replace(/^\.\s*/, '')}
                </p>
                {alert.account ? (
                    <p className="text-sm text-reos-tone-danger-fg">
                        {alert.account}
                    </p>
                ) : null}
            </div>
            <Button asChild variant="destructive">
                <a href={connect(alert.integration).url}>
                    <RefreshCcw aria-hidden />
                    {t('connections.alert.action')}
                </a>
            </Button>
        </section>
    );
}

/** Every connection of yours that needs a reconnect, at the top of each page. */
export function ConnectionAlerts() {
    const { t } = useTranslation();
    const { connectionAlerts } = usePage().props;

    if (connectionAlerts.length === 0) {
        return null;
    }

    return (
        <div
            role="region"
            aria-label={t('connections.alert.label')}
            className="mx-4 mt-4 space-y-3 md:mx-6"
        >
            {connectionAlerts.map((alert) => (
                <ConnectionAlert key={alert.id} alert={alert} />
            ))}
        </div>
    );
}
