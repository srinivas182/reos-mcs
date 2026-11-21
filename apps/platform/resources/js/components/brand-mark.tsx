import { cn } from '@/lib/utils';

/** The REOS mark: "RE" on action blue, as in every journey screen. */
export function BrandMark({ className }: { className?: string }) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex size-11 shrink-0 items-center justify-center rounded-reos-md bg-primary text-base font-bold text-primary-foreground',
                className,
            )}
        >
            RE
        </span>
    );
}
