import { Head, router } from '@inertiajs/react';
import { Download, MailPlus, ShieldCheck, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { InviteSheet } from '@/components/invite-sheet';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { roleName } from '@/lib/roles';
import { timeAgo } from '@/lib/time-ago';
import { cn } from '@/lib/utils';
import { exportMethod } from '@/routes/agents';
import { destroy, resend } from '@/routes/invites';
import type { AgentRow, InvitationRow, InviteOptions } from '@/types';

type Props = {
    tenant: { name: string; team: string | null };
    agents: AgentRow[];
    invitations: InvitationRow[];
    invite: InviteOptions;
};

const th = 'px-3 py-2 text-left';
const td = 'px-3 py-3 align-middle';

/**
 * Agents (broker and team leader, web journey screen 32), A4.3a: the
 * roster with team and last activity, open invitations with Resend and
 * Withdraw, the seats, Invite agents and Export. Mode, talks, deals, CE and
 * the "needs help" signal arrive with the agent activity they read.
 */
export default function Agents({ tenant, agents, invitations, invite }: Props) {
    const { t, i18n } = useTranslation();
    const [tab, setTab] = useState<'all' | 'invited'>('all');
    const [inviting, setInviting] = useState(false);
    const seats = invite.seats;
    const payer =
        seats.payer === 'organisation'
            ? t('invites.sheet.payers.organisation', { tenant: tenant.name })
            : t('invites.sheet.payers.member');
    const dateOf = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'short',
            day: 'numeric',
        }).format(new Date(iso));

    const inviteButton = (
        <Button onClick={() => setInviting(true)}>
            <MailPlus aria-hidden className="size-5" />
            {t('agents.invite')}
        </Button>
    );

    return (
        <>
            <Head title={t('agents.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('agents.title')}
                    description={t('agents.purpose', {
                        count: agents.length,
                        tenant: tenant.team ?? tenant.name,
                    })}
                    actions={
                        <>
                            <Button asChild variant="outline">
                                <a href={exportMethod().url}>
                                    <Download aria-hidden className="size-5" />
                                    {t('agents.export')}
                                </a>
                            </Button>
                            {inviteButton}
                        </>
                    }
                />

                <div
                    role="tablist"
                    aria-label={t('agents.tabsLabel')}
                    className="flex gap-1 border-b border-border"
                >
                    {(['all', 'invited'] as const).map((value) => (
                        <button
                            key={value}
                            type="button"
                            role="tab"
                            aria-selected={tab === value}
                            onClick={() => setTab(value)}
                            className={cn(
                                '-mb-px min-h-12 border-b-2 px-4 font-medium',
                                tab === value
                                    ? 'border-reos-action text-foreground'
                                    : 'border-transparent text-muted-foreground',
                            )}
                        >
                            {t(`agents.tabs.${value}`, {
                                count:
                                    value === 'all'
                                        ? agents.length
                                        : invitations.length,
                            })}
                        </button>
                    ))}
                </div>

                <p className="text-sm text-muted-foreground">
                    {seats.limit === null
                        ? t('agents.seatsNoLimit', { used: seats.used, payer })
                        : t('agents.seats', {
                              used: seats.used,
                              limit: seats.limit,
                              pending: seats.pending,
                              payer,
                          })}
                </p>

                {tab === 'all' ? (
                    agents.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={t('agents.empty.title')}
                            description={t('agents.empty.body')}
                            action={inviteButton}
                        />
                    ) : (
                        <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                            <table className="w-full min-w-[36rem] text-sm">
                                <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                    <tr>
                                        <th scope="col" className={th}>
                                            {t('agents.columns.agent')}
                                        </th>
                                        <th scope="col" className={th}>
                                            {t('agents.columns.team')}
                                        </th>
                                        <th scope="col" className={th}>
                                            {t('agents.columns.lastActive')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {agents.map((agent) => (
                                        <tr key={agent.id}>
                                            <th
                                                scope="row"
                                                className={cn(td, 'text-left')}
                                            >
                                                <span className="flex items-center gap-3">
                                                    <span
                                                        aria-hidden
                                                        className="flex size-9 shrink-0 items-center justify-center rounded-full bg-reos-tone-info-bg text-xs font-semibold text-reos-tone-info-fg"
                                                    >
                                                        {initials(agent.name)}
                                                    </span>
                                                    <span className="min-w-0">
                                                        <span className="block font-semibold">
                                                            {agent.name}
                                                        </span>
                                                        {agent.email ? (
                                                            <span className="block truncate font-normal text-muted-foreground">
                                                                {agent.email}
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </span>
                                            </th>
                                            <td className={td}>
                                                {agent.team ?? '—'}
                                            </td>
                                            <td className={td}>
                                                {agent.last_active
                                                    ? timeAgo(
                                                          agent.last_active,
                                                          i18n.language,
                                                      )
                                                    : t('agents.never')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )
                ) : invitations.length === 0 ? (
                    <EmptyState
                        icon={MailPlus}
                        title={t('agents.emptyInvites.title')}
                        description={t('agents.emptyInvites.body')}
                        action={inviteButton}
                    />
                ) : (
                    <section className="overflow-x-auto rounded-reos-lg border border-border bg-card p-2">
                        <table className="w-full min-w-[48rem] text-sm">
                            <thead className="border-b border-border text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                <tr>
                                    {(
                                        [
                                            'person',
                                            'role',
                                            'team',
                                            'sent',
                                            'expires',
                                            'status',
                                            'actions',
                                        ] as const
                                    ).map((column) => (
                                        <th
                                            key={column}
                                            scope="col"
                                            className={th}
                                        >
                                            {t(`agents.columns.${column}`)}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {invitations.map((invitation) => (
                                    <tr key={invitation.id}>
                                        <th
                                            scope="row"
                                            className={cn(td, 'text-left')}
                                        >
                                            <span className="block font-semibold break-all">
                                                {invitation.name ??
                                                    invitation.contact}
                                            </span>
                                            <span className="block font-normal text-muted-foreground">
                                                {invitation.name
                                                    ? `${invitation.contact} · `
                                                    : ''}
                                                {t(
                                                    `agents.channels.${invitation.channel}`,
                                                )}
                                            </span>
                                        </th>
                                        <td className={td}>
                                            {roleName(t, invitation.role)}
                                        </td>
                                        <td className={td}>
                                            {invitation.team ?? '—'}
                                        </td>
                                        <td className={td}>
                                            {invitation.sent_at
                                                ? dateOf(invitation.sent_at)
                                                : '—'}
                                        </td>
                                        <td className={td}>
                                            {dateOf(invitation.expires_at)}
                                            {invitation.reminded ? (
                                                <span className="block text-muted-foreground">
                                                    {t('agents.reminded')}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td className={td}>
                                            <span
                                                className={cn(
                                                    'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold whitespace-nowrap',
                                                    invitation.state ===
                                                        'expired'
                                                        ? 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg'
                                                        : 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
                                                )}
                                            >
                                                {t(
                                                    `agents.states.${invitation.state}`,
                                                )}
                                            </span>
                                        </td>
                                        <td className={td}>
                                            <span className="flex gap-1">
                                                <Button
                                                    variant="ghost"
                                                    aria-label={t(
                                                        'agents.resendTo',
                                                        {
                                                            contact:
                                                                invitation.contact,
                                                        },
                                                    )}
                                                    onClick={() =>
                                                        router.post(
                                                            resend(
                                                                invitation.id,
                                                            ).url,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {t('agents.resend')}
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    className="text-destructive"
                                                    aria-label={t(
                                                        'agents.revokeFor',
                                                        {
                                                            contact:
                                                                invitation.contact,
                                                        },
                                                    )}
                                                    onClick={() =>
                                                        router.delete(
                                                            destroy(
                                                                invitation.id,
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    {t('agents.revoke')}
                                                </Button>
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                )}

                <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                    <ShieldCheck aria-hidden className="size-4 shrink-0" />
                    {t('agents.privacy')}
                </p>
            </div>

            <InviteSheet
                open={inviting}
                onOpenChange={setInviting}
                options={invite}
                tenant={tenant.name}
            />
        </>
    );
}

function initials(name: string): string {
    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
