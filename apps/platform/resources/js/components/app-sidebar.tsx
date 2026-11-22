import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { roleNavigation } from '@/lib/navigation';
import { activeMembership } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

/**
 * The web journey's sidebar (page 4): REOS mark, the organisation you're
 * working in, then the menu for your role. Dark navy in every theme.
 */
export function AppSidebar() {
    const { t } = useTranslation();
    const { auth, messages, sessions } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const current = activeMembership(auth.roles);
    const items = roleNavigation(current?.role ?? null, {
        messages: messages !== null,
        sessions: sessions !== null,
    });
    const organisation = current?.organisation;
    // People on their own (REOS's own organisation, not super admins) have no brand to show.
    const showOrganisation =
        organisation !== undefined &&
        (organisation.type !== 'platform' || current?.role === 'super_admin');

    return (
        <Sidebar collapsible="offcanvas" variant="sidebar">
            <SidebarHeader className="gap-4 px-4 pt-5">
                <Link
                    href={dashboard()}
                    className="flex min-h-12 items-center gap-3 rounded-reos-md focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none"
                >
                    <span
                        aria-hidden
                        className="flex size-9 items-center justify-center rounded-reos-sm bg-sidebar-primary text-sm font-bold text-sidebar-primary-foreground"
                    >
                        RE
                    </span>
                    <span className="text-lg font-bold">REOS</span>
                </Link>

                {showOrganisation ? (
                    <div className="rounded-reos-md bg-sidebar-accent px-3 py-2.5">
                        <p className="text-sm text-reos-nav-muted">
                            {organisation.type === 'platform'
                                ? t('nav.platform')
                                : t('nav.organisation')}
                        </p>
                        <p className="truncate font-semibold">
                            {organisation.name}
                        </p>
                    </div>
                ) : null}
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup className="px-3">
                    <nav aria-label={t('nav.mainMenu')}>
                        <SidebarMenu className="gap-1">
                            {items.map((entry) => {
                                const active = isCurrentOrParentUrl(
                                    entry.section ?? entry.href,
                                );

                                return (
                                    <SidebarMenuItem key={entry.label}>
                                        <SidebarMenuButton
                                            asChild
                                            isActive={active}
                                            className={cn(
                                                'h-12 gap-3 rounded-reos-md px-3 text-base text-sidebar-foreground [&>svg]:size-5',
                                                'data-[active=true]:bg-sidebar-primary data-[active=true]:text-sidebar-primary-foreground',
                                            )}
                                        >
                                            <Link
                                                href={entry.href}
                                                prefetch
                                                aria-current={
                                                    active ? 'page' : undefined
                                                }
                                            >
                                                <entry.icon aria-hidden />
                                                <span className="flex-1">
                                                    {t(`nav.${entry.label}`)}
                                                </span>
                                                {entry.label === 'messages' &&
                                                messages !== null &&
                                                messages.unread > 0 ? (
                                                    <span
                                                        className="rounded-full bg-reos-tone-danger-fg px-2 text-sm font-semibold text-background"
                                                        aria-label={t(
                                                            'messages.unreadCount',
                                                            {
                                                                count: messages.unread,
                                                            },
                                                        )}
                                                    >
                                                        {messages.unread}
                                                    </span>
                                                ) : null}
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                );
                            })}
                        </SidebarMenu>
                    </nav>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    );
}
