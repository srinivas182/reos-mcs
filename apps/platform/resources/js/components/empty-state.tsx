import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type EmptyStateProps = {
    icon: LucideIcon;
    /** What this place is for, in plain words: "Your contacts live here". */
    title: string;
    /** One or two sentences that teach what to do next. */
    description: string;
    /** The one main action (usually a Button). */
    action?: ReactNode;
    /** Optional second, quieter action (for example "Import a spreadsheet"). */
    secondaryAction?: ReactNode;
    className?: string;
};

/**
 * Shown when a list or page has nothing in it yet. Teaches what the page is for
 * and offers exactly one main next step, so first-time users are never stuck.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    secondaryAction,
    className,
}: EmptyStateProps) {
    return (
        <section
            aria-label={title}
            className={cn(
                'flex flex-col items-center gap-4 rounded-reos-lg border border-dashed border-border bg-card px-6 py-12 text-center',
                className,
            )}
        >
            <span className="flex size-14 items-center justify-center rounded-reos-pill bg-accent text-primary">
                <Icon aria-hidden className="size-7" />
            </span>
            <div className="max-w-md space-y-2">
                <h2 className="text-title font-semibold text-foreground">
                    {title}
                </h2>
                <p className="text-body text-muted-foreground">{description}</p>
            </div>
            {action || secondaryAction ? (
                <div className="flex flex-col items-center gap-3 sm:flex-row">
                    {action}
                    {secondaryAction}
                </div>
            ) : null}
        </section>
    );
}
