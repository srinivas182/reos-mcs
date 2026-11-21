import { useRef } from 'react';
import type { KeyboardEvent } from 'react';
import { cn } from '@/lib/utils';

export type Pill<T extends string> = { value: T; label: string; lang?: string };

/**
 * A row of rounded choices ("Jan · Feb · Mar · Not sure"), as on "Your plan ·
 * 3 quick questions". A radio group: arrow keys move and select, Tab leaves.
 * Each pill is at least 48 px tall.
 */
export function PillGroup<T extends string>({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: T | null;
    options: readonly Pill<T>[];
    onChange: (value: T) => void;
}) {
    const refs = useRef<Array<HTMLButtonElement | null>>([]);
    const selected = options.findIndex((option) => option.value === value);
    const focusable = selected === -1 ? 0 : selected;

    const move = (event: KeyboardEvent<HTMLButtonElement>, index: number) => {
        const step =
            event.key === 'ArrowRight' || event.key === 'ArrowDown'
                ? 1
                : event.key === 'ArrowLeft' || event.key === 'ArrowUp'
                  ? -1
                  : 0;

        if (step === 0) {
            return;
        }

        event.preventDefault();
        const next = (index + step + options.length) % options.length;
        onChange(options[next].value);
        refs.current[next]?.focus();
    };

    return (
        <div
            role="radiogroup"
            aria-label={label}
            className="flex flex-wrap gap-2"
        >
            {options.map((option, index) => {
                const checked = option.value === value;

                return (
                    <button
                        key={option.value}
                        ref={(element) => {
                            refs.current[index] = element;
                        }}
                        type="button"
                        role="radio"
                        lang={option.lang}
                        aria-checked={checked}
                        tabIndex={index === focusable ? 0 : -1}
                        onClick={() => onChange(option.value)}
                        onKeyDown={(event) => move(event, index)}
                        className={cn(
                            'min-h-12 rounded-reos-pill border px-5 text-base font-semibold transition-colors',
                            checked
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-border bg-card text-foreground hover:bg-muted',
                        )}
                    >
                        {option.label}
                    </button>
                );
            })}
        </div>
    );
}
