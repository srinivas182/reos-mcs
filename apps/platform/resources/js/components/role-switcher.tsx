import { router, usePage } from '@inertiajs/react';
import { ChevronDown, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { activeMembership, membershipLabel, roleName } from '@/lib/roles';
import { update as updateActiveRole } from '@/routes/active-role';
import type { Membership, Roles } from '@/types/auth';

const keyOf = (m: Membership): string => `${m.role}:${m.organisation.id}`;

type Props = {
    roles: Roles;
    onSwitch: (membership: Membership) => void;
    switching?: boolean;
};

/**
 * The role chip in the top bar (web journey, Navigation): names the role in
 * use and, for people with more than one, switches it. The browser
 * remembers the choice; the phone app switches in Me.
 */
export function RoleSwitcher({ roles, onSwitch, switching = false }: Props) {
    const { t } = useTranslation();
    const current = activeMembership(roles);

    if (current === null) {
        return null;
    }

    const label = membershipLabel(t, current);
    const needsTwoStep = (m: Membership): boolean =>
        m.admin && !roles.twoStepEnabled;

    const chip =
        'inline-flex items-center gap-1 rounded-reos-pill bg-accent px-3 py-1 text-sm font-semibold text-primary';
    const role = roleName(t, current.role);

    // One role: the chip just names it.
    if (roles.held.length < 2) {
        return (
            <p
                className="flex min-h-12 items-center"
                aria-label={`${t('roles.switcher.label')}: ${label}`}
            >
                <span className={chip}>{role}</span>
            </p>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex min-h-12 items-center rounded-reos-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50"
                    disabled={switching}
                    aria-label={`${t('roles.switcher.label')}: ${label}. ${t('roles.switcher.menuTitle')}`}
                >
                    <span className={chip}>
                        {role}
                        <ChevronDown aria-hidden className="size-4" />
                    </span>
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-72">
                <DropdownMenuLabel>
                    {t('roles.switcher.menuTitle')}
                </DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={keyOf(current)}
                    onValueChange={(value) => {
                        const next = roles.held.find((m) => keyOf(m) === value);

                        if (next && value !== keyOf(current)) {
                            onSwitch(next);
                        }
                    }}
                >
                    {roles.held.map((membership) => (
                        <DropdownMenuRadioItem
                            key={keyOf(membership)}
                            value={keyOf(membership)}
                            className="min-h-12 py-2 text-base"
                        >
                            <span className="flex flex-col">
                                <span>{membershipLabel(t, membership)}</span>
                                {needsTwoStep(membership) ? (
                                    <span className="flex items-center gap-1 text-sm text-muted-foreground">
                                        <ShieldAlert
                                            aria-hidden
                                            className="size-4"
                                        />
                                        {t('roles.switcher.needsTwoStep')}
                                    </span>
                                ) : null}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

/** The switcher in the app header, wired to the server. */
export function HeaderRoleSwitcher() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const [switching, setSwitching] = useState(false);

    if (!auth.roles) {
        return null;
    }

    return (
        <RoleSwitcher
            roles={auth.roles}
            switching={switching}
            onSwitch={(membership) =>
                router.put(
                    updateActiveRole().url,
                    {
                        role: membership.role,
                        organisation_id: membership.organisation.id,
                    },
                    {
                        onStart: () => setSwitching(true),
                        onFinish: () => setSwitching(false),
                        onSuccess: () =>
                            toast.success(
                                t('roles.switcher.switched', {
                                    role: roleName(t, membership.role),
                                }),
                            ),
                    },
                )
            }
        />
    );
}
