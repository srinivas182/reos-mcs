import { BriefcaseBusiness, ShieldCheck, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Button } from '@/components/ui/button';
import { roleName } from '@/lib/roles';
import type { Membership } from '@/types/auth';

type Props = {
    memberships: Membership[];
    /** Offer "Remove" on each role (People screen). */
    onRemove?: (membership: Membership) => void;
    disabled?: boolean;
};

/** The roles a person holds, each with its organisation. */
export function MembershipList({ memberships, onRemove, disabled }: Props) {
    const { t } = useTranslation();

    if (memberships.length === 0) {
        return (
            <EmptyState
                icon={BriefcaseBusiness}
                title={t('people.roles.emptyTitle')}
                description={t('people.roles.emptyDescription')}
            />
        );
    }

    return (
        <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
            {memberships.map((membership) => {
                const role = roleName(t, membership.role);

                return (
                    <li
                        key={`${membership.role}:${membership.organisation.id}`}
                        className="flex flex-wrap items-center gap-4 p-4"
                    >
                        <span className="flex size-12 shrink-0 items-center justify-center rounded-reos-pill bg-accent text-primary">
                            {membership.admin ? (
                                <ShieldCheck aria-hidden className="size-6" />
                            ) : (
                                <BriefcaseBusiness
                                    aria-hidden
                                    className="size-6"
                                />
                            )}
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-semibold text-foreground">
                                {role}
                                {membership.admin ? (
                                    <span className="ml-2 rounded-reos-pill bg-muted px-2 py-0.5 text-sm font-medium text-muted-foreground">
                                        {t('people.admin')}
                                    </span>
                                ) : null}
                            </p>
                            <p className="text-body text-muted-foreground">
                                {membership.organisation.name} ·{' '}
                                {t(
                                    `roles.organisationTypes.${membership.organisation.type}`,
                                )}
                            </p>
                        </div>
                        {onRemove ? (
                            <Button
                                variant="outline"
                                disabled={disabled}
                                onClick={() => onRemove(membership)}
                                aria-label={t('people.remove.label', {
                                    role,
                                    organisation: membership.organisation.name,
                                })}
                            >
                                <X aria-hidden />
                                {t('people.remove.button')}
                            </Button>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}
