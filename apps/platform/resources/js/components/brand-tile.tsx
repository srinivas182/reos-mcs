import { toolInitials } from '@/lib/tool-initials';
import { cn } from '@/lib/utils';

/** A tenant's logo, or its initials on its colour (A4.2a). */
export function BrandTile({
    name,
    logoUrl,
    colour,
    className,
}: {
    name: string;
    logoUrl: string | null;
    colour: string | null;
    className?: string;
}) {
    return logoUrl ? (
        <img
            src={logoUrl}
            alt=""
            className={cn(
                'size-10 shrink-0 rounded-reos-md bg-white object-contain p-1',
                className,
            )}
        />
    ) : (
        <span
            aria-hidden
            className={cn(
                'flex size-10 shrink-0 items-center justify-center rounded-reos-md bg-primary text-sm font-bold text-primary-foreground',
                className,
            )}
            style={
                colour
                    ? { backgroundColor: colour, color: '#FFFFFF' }
                    : undefined
            }
        >
            {toolInitials(name)}
        </span>
    );
}
