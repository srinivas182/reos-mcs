import type { ComponentProps } from 'react';
import { cn } from '@/lib/utils';

type SwitchProps = Omit<ComponentProps<'button'>, 'onChange' | 'role'> & {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
};

/**
 * An on/off switch (feature switches, screen 60). A real button with
 * `role="switch"`, so screen readers say "on" or "off"; the touch target is
 * 48 px even though the track is smaller.
 */
function Switch({ checked, onCheckedChange, className, disabled, ...props }: SwitchProps) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={checked}
            disabled={disabled}
            onClick={() => onCheckedChange(!checked)}
            className={cn(
                'group inline-flex size-12 shrink-0 items-center justify-center rounded-reos-pill focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:opacity-50',
                className,
            )}
            {...props}
        >
            <span
                aria-hidden
                className={cn(
                    'relative block h-6 w-11 rounded-reos-pill transition-colors',
                    checked ? 'bg-primary' : 'bg-input',
                )}
            >
                <span
                    className={cn(
                        'absolute top-0.5 left-0.5 block size-5 rounded-full bg-background shadow transition-transform',
                        checked ? 'translate-x-5' : 'translate-x-0',
                    )}
                />
            </span>
        </button>
    );
}

export { Switch };
