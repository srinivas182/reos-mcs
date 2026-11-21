import { Link, router, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { activeMembership, roleName } from '@/lib/roles';
import { cn } from '@/lib/utils';
import { update as updateActiveRole } from '@/routes/active-role';
import { edit as editSecurity } from '@/routes/security';

type Props = {
    /** The admin role's name, such as "Broker". */
    role: string;
    /** `app`: the banner on every page; `security`: the note on Security settings. */
    mode?: 'app' | 'security';
    action?: ReactNode;
    secondaryAction?: ReactNode;
    className?: string;
};

/**
 * Admin roles need two-step. Instead of a locked door, this says why and
 * leads straight to setting it up (A3.2).
 */
export function TwoStepNotice({
    role,
    mode = 'app',
    action,
    secondaryAction,
    className,
}: Props) {
    const { t } = useTranslation();
    const title =
        mode === 'app'
            ? t('roles.twoStepNotice.title', { role })
            : t('roles.twoStepSecurity.title', { role });
    const body =
        mode === 'app'
            ? t('roles.twoStepNotice.body')
            : t('roles.twoStepSecurity.body');

    return (
        <section
            aria-label={title}
            className={cn(
                'flex flex-col gap-4 rounded-reos-lg border border-primary/30 bg-accent p-5 sm:flex-row sm:items-start',
                className,
            )}
        >
            <span className="flex size-12 shrink-0 items-center justify-center rounded-reos-pill bg-card text-primary">
                <ShieldCheck aria-hidden className="size-6" />
            </span>
            <div className="min-w-0 flex-1 space-y-2">
                <h2 className="text-title font-semibold text-foreground">
                    {title}
                </h2>
                <p className="text-body text-foreground">{body}</p>
                {action || secondaryAction ? (
                    <div className="flex flex-wrap gap-3 pt-2">
                        {action}
                        {secondaryAction}
                    </div>
                ) : null}
            </div>
        </section>
    );
}

/**
 * The banner on every page while an admin role is waiting for two-step.
 * Security settings shows its own note instead.
 */
export function AdminTwoStepBanner() {
    const { t } = useTranslation();
    const { auth } = usePage().props;
    const { isCurrentUrl } = useCurrentUrl();
    const current = activeMembership(auth.roles);

    if (
        !auth.roles?.twoStepRequired ||
        current === null ||
        isCurrentUrl(editSecurity())
    ) {
        return null;
    }

    const other = auth.roles.held.find((m) => !m.admin);

    return (
        <TwoStepNotice
            className="mx-4 mt-4 md:mx-6"
            role={roleName(t, current.role)}
            action={
                <Button asChild>
                    <Link href={editSecurity()}>
                        {t('roles.twoStepNotice.action')}
                    </Link>
                </Button>
            }
            secondaryAction={
                other ? (
                    <Button
                        variant="outline"
                        onClick={() =>
                            router.put(updateActiveRole().url, {
                                role: other.role,
                                organisation_id: other.organisation.id,
                            })
                        }
                    >
                        {t('roles.twoStepNotice.otherRole')}
                    </Button>
                ) : null
            }
        />
    );
}
