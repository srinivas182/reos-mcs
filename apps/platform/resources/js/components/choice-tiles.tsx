import type { LucideIcon } from 'lucide-react';
import { Check } from 'lucide-react';
import { useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type ChoiceTile<T extends string> = {
    value: T;
    label: string;
    description?: string;
    icon?: LucideIcon;
    /** Optional preview drawn inside the tile (for example a sample of text). */
    preview?: ReactNode;
    /** Shown but not selectable; say why in `description`. */
    disabled?: boolean;
};

type ChoiceTilesProps<T extends string> = {
    /** Visible group label, also read by screen readers. */
    label: string;
    value: T;
    options: readonly ChoiceTile<T>[];
    onChange: (value: T) => void;
    className?: string;
};

/**
 * A single-choice group shown as large tiles (at least 48 px tall, whole tile
 * clickable). Behaves like a native radio group: Tab focuses the selected tile,
 * arrow keys move and select, and screen readers announce "radio, 2 of 4".
 */
export function ChoiceTiles<T extends string>({
    label,
    value,
    options,
    onChange,
    className,
}: ChoiceTilesProps<T>) {
    const refs = useRef<Array<HTMLButtonElement | null>>([]);
    const labelId = `choice-${label.toLowerCase().replace(/\W+/g, '-')}`;

    const move = (
        event: KeyboardEvent<HTMLButtonElement>,
        index: number,
    ): void => {
        const step = {
            ArrowRight: 1,
            ArrowDown: 1,
            ArrowLeft: -1,
            ArrowUp: -1,
        }[event.key];

        if (step === undefined) {
            return;
        }

        event.preventDefault();

        // Skip tiles that can't be chosen, like a native radio group does.
        for (let offset = 1; offset < options.length; offset++) {
            const next =
                (index + step * offset + options.length * offset) %
                options.length;
            const option = options[next];

            if (option && !option.disabled) {
                onChange(option.value);
                refs.current[next]?.focus();

                return;
            }
        }
    };

    return (
        <div className={cn('space-y-3', className)}>
            <p id={labelId} className="text-body font-semibold text-foreground">
                {label}
            </p>
            <div
                role="radiogroup"
                aria-labelledby={labelId}
                className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
            >
                {options.map((option, index) => {
                    const selected = option.value === value;
                    const Icon = option.icon;

                    return (
                        <button
                            key={option.value}
                            ref={(element) => {
                                refs.current[index] = element;
                            }}
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            tabIndex={selected ? 0 : -1}
                            disabled={option.disabled}
                            onClick={() => onChange(option.value)}
                            onKeyDown={(event) => move(event, index)}
                            className={cn(
                                'relative flex min-h-touch flex-col items-start gap-1 rounded-reos-md border-2 bg-card p-4 text-left transition-colors',
                                'hover:border-primary/60 disabled:cursor-not-allowed disabled:border-dashed disabled:bg-muted disabled:hover:border-border',
                                selected
                                    ? 'border-primary bg-accent'
                                    : 'border-border',
                            )}
                        >
                            <span className="flex w-full items-center gap-2 font-semibold text-foreground">
                                {Icon ? (
                                    <Icon
                                        aria-hidden
                                        className="size-5 shrink-0"
                                    />
                                ) : null}
                                <span className="min-w-0 flex-1">
                                    {option.label}
                                </span>
                                {selected ? (
                                    <Check
                                        aria-hidden
                                        className="size-5 shrink-0 text-primary"
                                    />
                                ) : null}
                            </span>
                            {option.description ? (
                                <span className="text-caption text-muted-foreground">
                                    {option.description}
                                </span>
                            ) : null}
                            {option.preview}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
