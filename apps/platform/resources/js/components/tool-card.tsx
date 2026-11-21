import { useTranslation } from 'react-i18next';
import { CatalogueStatusBadge } from '@/components/catalogue-status-badge';
import { Button } from '@/components/ui/button';
import { toolInitials } from '@/lib/tool-initials';
import { cn } from '@/lib/utils';
import { connect } from '@/routes/connections';
import type { CatalogueItem, ToolConnection } from '@/types';

type Props = {
    tool: CatalogueItem;
    /** This person's account at the tool, when connected. */
    connection?: ToolConnection;
    onManage?: (connection: ToolConnection) => void;
    /** "Notify me" or "Join beta". */
    onFollow?: (tool: CatalogueItem) => void;
};

const chip =
    'inline-flex items-center rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap';

/**
 * One tool on Connections (web journey screen 19): its tile, name, type and
 * what it does, a status (Connected, Available, Beta, Coming soon,
 * Temporarily unavailable) and the one thing to do next.
 */
export function ToolCard({ tool, connection, onManage, onFollow }: Props) {
    const { t, i18n } = useTranslation();
    const needsSignIn = connection?.status === 'reconnect_needed';

    const status = connection ? (
        <span
            className={cn(
                chip,
                needsSignIn
                    ? 'bg-reos-tone-danger-bg text-reos-tone-danger-fg'
                    : 'bg-reos-tone-success-bg text-reos-tone-success-fg',
            )}
        >
            {needsSignIn
                ? t('connections.web.needsSignIn')
                : t('connections.web.connected')}
        </span>
    ) : (
        <CatalogueStatusBadge status={tool.status} audience="people" />
    );

    const quiet = (text: string) => (
        <span className="text-sm text-muted-foreground">{text}</span>
    );

    const action = (() => {
        if (connection && needsSignIn) {
            return (
                <Button asChild>
                    <a href={connect(tool.id).url}>
                        {t('connections.state.reconnect')}
                    </a>
                </Button>
            );
        }

        if (connection) {
            return (
                <Button
                    variant="outline"
                    onClick={() => onManage?.(connection)}
                    aria-label={t('connections.web.manageTool', {
                        tool: tool.name,
                    })}
                >
                    {t('connections.web.manage')}
                </Button>
            );
        }

        if (tool.action === 'connect') {
            // Tools the brokerage owns are connected once, for every agent.
            if (tool.accounts === 'tenant') {
                return quiet(t('connections.web.brokerage'));
            }

            return (
                <Button asChild>
                    <a
                        href={connect(tool.id).url}
                        aria-label={t('connections.web.connectTool', {
                            tool: tool.name,
                        })}
                    >
                        {t('connections.library.connect')}
                    </a>
                </Button>
            );
        }

        if (tool.action === 'notify' || tool.action === 'join_beta') {
            const label =
                tool.action === 'notify'
                    ? t('connections.library.notify')
                    : t('connections.library.joinBeta');

            if (tool.interested) {
                return quiet(
                    tool.action === 'notify'
                        ? t('connections.library.notified')
                        : t('connections.library.asked'),
                );
            }

            return (
                <Button
                    variant="outline"
                    onClick={() => onFollow?.(tool)}
                    aria-label={`${label} · ${tool.name}`}
                >
                    {label}
                </Button>
            );
        }

        if (tool.status === 'retired' && tool.retires_on) {
            return quiet(
                t('connections.library.retiring', {
                    date: new Intl.DateTimeFormat(i18n.language, {
                        month: 'short',
                        day: 'numeric',
                    }).format(new Date(`${tool.retires_on}T12:00:00`)),
                }),
            );
        }

        return quiet(t('connections.web.backSoon'));
    })();

    return (
        <article
            aria-label={tool.name}
            className="flex flex-col gap-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex items-start gap-3">
                <span
                    aria-hidden
                    className="flex size-11 shrink-0 items-center justify-center rounded-reos-md bg-primary text-sm font-bold text-primary-foreground"
                >
                    {toolInitials(tool.name)}
                </span>
                <div className="min-w-0">
                    <h2 className="text-body font-semibold text-foreground">
                        {tool.name}
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        {t(`integrations.categories.${tool.category}`)}
                    </p>
                </div>
            </div>
            <p className="flex-1 text-body text-muted-foreground">
                {tool.purpose}
            </p>
            <div className="flex flex-wrap items-center justify-between gap-3">
                {status}
                {action}
            </div>
        </article>
    );
}
