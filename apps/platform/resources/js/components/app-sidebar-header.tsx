import { Link, usePage } from '@inertiajs/react';
import { Bell, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { HeaderRoleSwitcher } from '@/components/role-switcher';
import { StateSwitcher } from '@/components/state-switcher';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { activeMembership } from '@/lib/roles';
import { index as learn } from '@/routes/learn';
import { index as notificationsIndex } from '@/routes/notifications';
import type { RoleName } from '@/types/auth';

/** Roles that learn: the top bar searches their lessons (A5.3b). */
const LEARNERS: RoleName[] = ['candidate', 'agent', 'broker'];

/**
 * The web journey's top bar: state chip (which switches state, A5.1), role
 * chip (which switches role), then the person and their organisation.
 * Learners get the search box, which looks inside their lessons (A5.3b);
 * the bell opens the notification inbox (B6.2); questions to Ask and Help
 * arrive with their own sprints.
 */
export function AppSidebarHeader() {
    const { t } = useTranslation();
    const { auth, region, notifications } = usePage().props;
    const unread = notifications?.unread ?? 0;
    const getInitials = useInitials();
    const current = activeMembership(auth.roles);
    const name = auth.user.name ?? auth.user.email ?? auth.user.phone ?? '';

    return (
        <header className="flex min-h-16 shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6 print:hidden">
            <SidebarTrigger
                className="size-12 md:hidden"
                aria-label={t('nav.openMenu')}
            />

            {current && LEARNERS.includes(current.role) ? (
                <form
                    action={learn().url}
                    method="get"
                    role="search"
                    className="hidden max-w-md flex-1 md:block"
                >
                    <label htmlFor="top-search" className="sr-only">
                        {t('nav.search')}
                    </label>
                    <div className="relative">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute top-1/2 left-3 size-5 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            id="top-search"
                            name="q"
                            type="search"
                            maxLength={300}
                            placeholder={t('nav.search')}
                            className="pl-10"
                        />
                    </div>
                </form>
            ) : null}

            <div className="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                {region && current?.role !== 'super_admin' ? (
                    <StateSwitcher region={region} />
                ) : null}

                <HeaderRoleSwitcher />

                {/* The bell: the notification inbox (B6.2). */}
                <Link
                    href={notificationsIndex()}
                    className="relative flex size-12 items-center justify-center rounded-reos-md hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    aria-label={
                        unread > 0
                            ? t('notifications.bellUnread', { count: unread })
                            : t('notifications.bell')
                    }
                >
                    <Bell aria-hidden className="size-6" />
                    {unread > 0 ? (
                        <span
                            aria-hidden
                            className="absolute top-1.5 right-1.5 min-w-5 rounded-full bg-reos-tone-danger-fg px-1 text-center text-xs font-semibold text-background"
                        >
                            {unread > 99 ? '99+' : unread}
                        </span>
                    ) : null}
                </Link>

                <DropdownMenu>
                    <DropdownMenuTrigger
                        className="flex min-h-12 min-w-12 items-center gap-3 rounded-reos-md px-1 text-left focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        aria-label={t('nav.userMenu', { name })}
                        data-test="sidebar-menu-button"
                    >
                        <span
                            aria-hidden
                            className="flex size-10 shrink-0 items-center justify-center rounded-reos-pill bg-accent text-sm font-bold text-primary"
                        >
                            {getInitials(auth.user.name ?? name)}
                        </span>
                        <span className="hidden min-w-0 flex-col leading-tight lg:flex">
                            <span className="truncate font-semibold text-foreground">
                                {name}
                            </span>
                            {current ? (
                                <span className="truncate text-sm text-muted-foreground">
                                    {current.organisation.name}
                                </span>
                            ) : null}
                        </span>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="min-w-64">
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
