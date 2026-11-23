import { BadgeCheck, Building2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { MemberRow } from './types';

/**
 * An MLS member record found by the agent's license number (B10.1): "Yes,
 * this is me" links it; "Not me" stops suggesting it.
 */
export function MemberCard({
    member,
    busy = false,
    onConfirm,
    onDecline,
}: {
    member: MemberRow;
    busy?: boolean;
    onConfirm: () => void;
    onDecline: () => void;
}) {
    const { t } = useTranslation();

    return (
        <section
            aria-labelledby={`member-${member.member_key}`}
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2
                id={`member-${member.member_key}`}
                className="text-lg font-semibold"
            >
                {t('listings.found.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('listings.found.description', { mls: member.short_name })}
            </p>
            <div className="space-y-1">
                <p className="flex items-center gap-2 text-base font-semibold">
                    <BadgeCheck aria-hidden className="size-5" />
                    {member.name}
                </p>
                {member.office ? (
                    <p className="flex items-center gap-2 text-base text-muted-foreground">
                        <Building2 aria-hidden className="size-5" />
                        {[member.office, member.office_city]
                            .filter(Boolean)
                            .join(', ')}
                    </p>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-3">
                <Button disabled={busy} onClick={onConfirm}>
                    {t('listings.found.confirm')}
                </Button>
                <Button variant="outline" disabled={busy} onClick={onDecline}>
                    {t('listings.found.decline')}
                </Button>
            </div>
        </section>
    );
}
