import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    /** Buttons on the right, such as "Add integration". */
    actions?: ReactNode;
    /** A back link above the title. */
    back?: ReactNode;
    className?: string;
};

/**
 * Every web journey page starts the same way: a title, one line saying what
 * the page is for, and the main actions on the right.
 */
export function PageHeader({
    title,
    description,
    actions,
    back,
    className,
}: Props) {
    return (
        <header className={cn('space-y-3', className)}>
            {back}
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="min-w-0 space-y-1">
                    <h1 className="text-title font-semibold text-foreground">
                        {title}
                    </h1>
                    {description ? (
                        <p className="text-body text-muted-foreground">
                            {description}
                        </p>
                    ) : null}
                </div>
                {actions ? (
                    <div className="flex flex-wrap items-center gap-3">
                        {actions}
                    </div>
                ) : null}
            </div>
        </header>
    );
}
