import { router, usePage } from '@inertiajs/react';
import { BriefcaseBusiness, ChevronsUpDown, ShieldAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
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
 * Shows the role in use and, for people with more than one, switches it.
 * The browser remembers the choice; the phone app has its own in Me.
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

    // One role: say which, nothing to switch.
    if (roles.held.length < 2) {
        return (
            <p className="flex min-w-0 items-center gap-2 text-base text-muted-foreground">
                <BriefcaseBusiness aria-hidden className="size-5 shrink-0" />
                <span className="sr-only">{t('roles.switcher.label')}: </span>
                <span className="truncate">{label}</span>
            </p>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="outline"
                    className="max-w-full min-w-0"
                    disabled={switching}
                    aria-label={`${t('roles.switcher.label')}: ${label}. ${t('roles.switcher.menuTitle')}`}
                >
                    <BriefcaseBusiness aria-hidden />
                    <span className="truncate">{label}</span>
                    <ChevronsUpDown
                        aria-hidden
                        className="text-muted-foreground"
                    />
                </Button>
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
