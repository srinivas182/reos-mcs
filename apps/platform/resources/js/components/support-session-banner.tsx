import { router, usePage } from '@inertiajs/react';
import { LifeBuoy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/support-session';

/** Whole minutes left, never below zero. */
export function minutesLeft(
    endsAt: string | null,
    now: number = Date.now(),
): number {
    return endsAt === null
        ? 0
        : Math.max(0, Math.ceil((new Date(endsAt).getTime() - now) / 60_000));
}

/**
 * Shown on every page while REOS support is inside a tenant with the
 * owner's consent (A4.1c): where, how long is left, that every page is
 * logged, and a way out.
 */
export function SupportSessionBanner() {
    // Not `support`: pages such as Tenant detail have their own prop of that name.
    const { supportSession } = usePage().props;

    return supportSession ? (
        <SupportSessionNotice
            tenant={supportSession.tenant}
            endsAt={supportSession.ends_at}
        />
    ) : null;
}

export function SupportSessionNotice({
    tenant,
    endsAt,
}: {
    tenant: string;
    endsAt: string | null;
}) {
    const { t } = useTranslation();
    const [left, setLeft] = useState(() => minutesLeft(endsAt));

    useEffect(() => {
        const timer = window.setInterval(
            () => setLeft(minutesLeft(endsAt)),
            30_000,
        );

        return () => window.clearInterval(timer);
    }, [endsAt]);

    return (
        <section
            aria-label={t('support.banner.label')}
            className="sticky top-0 z-30 flex flex-wrap items-center gap-3 bg-reos-tone-warning-bg px-4 py-2 text-reos-tone-warning-fg md:px-8"
        >
            <LifeBuoy aria-hidden className="size-5 shrink-0" />
            <p className="min-w-0 flex-1 font-semibold">
                {t('support.banner.text', {
                    tenant,
                    left: t('support.banner.minutes', { count: left }),
                })}
            </p>
            <Button
                variant="outline"
                onClick={() => router.delete(destroy().url)}
            >
                {t('support.banner.end')}
            </Button>
        </section>
    );
}
