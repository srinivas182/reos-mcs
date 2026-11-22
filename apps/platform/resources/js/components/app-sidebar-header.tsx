import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { HeaderRoleSwitcher } from '@/components/role-switcher';
import { StateSwitcher } from '@/components/state-switcher';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { activeMembership } from '@/lib/roles';

/**
 * The web journey's top bar: state chip (which switches state, A5.1), role
 * chip (which switches role), then the person and their organisation. Search, the bell and Help arrive
 * with their own sprints.
 */
export function AppSidebarHeader() {
    const { t } = useTranslation();
    const { auth, region } = usePage().props;
    const getInitials = useInitials();
    const current = activeMembership(auth.roles);
    const name = auth.user.name ?? auth.user.email ?? auth.user.phone ?? '';

    return (
        <header className="flex min-h-16 shrink-0 items-center gap-3 border-b border-border bg-card px-4 md:px-6">
            <SidebarTrigger
                className="size-12 md:hidden"
                aria-label={t('nav.openMenu')}
            />

            <div className="ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                {region && current?.role !== 'super_admin' ? (
                    <StateSwitcher region={region} />
                ) : null}

                <HeaderRoleSwitcher />

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
