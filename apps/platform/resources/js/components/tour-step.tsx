import { X } from 'lucide-react';
import { useRef } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverAnchor,
    PopoverContent,
} from '@/components/ui/popover';

type TourStepProps = {
    /** Whether this step is showing. */
    open: boolean;
    /** The element the step points at. */
    children: ReactNode;
    title: string;
    body: string;
    /** "2 of 5". */
    step: number;
    total: number;
    onNext: () => void;
    onSkip: () => void;
    side?: 'top' | 'right' | 'bottom' | 'left';
};

/**
 * One step of a guided first-use tour: a short card pointing at a real part of
 * the screen, with Next and Skip. Focus moves to the card so keyboard and
 * screen-reader users follow along; Escape skips the tour.
 */
export function TourStep({
    open,
    children,
    title,
    body,
    step,
    total,
    onNext,
    onSkip,
    side = 'bottom',
}: TourStepProps) {
    const nextRef = useRef<HTMLButtonElement>(null);
    const last = step === total;

    return (
        <Popover
            open={open}
            onOpenChange={(next) => (next ? undefined : onSkip())}
        >
            <PopoverAnchor asChild>{children}</PopoverAnchor>
            <PopoverContent
                side={side}
                // Start on the main action (Next or Done), not the close button the popover would pick.
                onOpenAutoFocus={(event) => {
                    event.preventDefault();
                    nextRef.current?.focus();
                }}
                className="w-80 space-y-3 p-5"
                aria-label={`Tour step ${step} of ${total}: ${title}`}
            >
                <div className="flex items-start justify-between gap-2">
                    <p className="text-caption font-semibold text-primary">
                        Step {step} of {total}
                    </p>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={onSkip}
                        aria-label="Skip the tour"
                    >
                        <X />
                    </Button>
                </div>
                <h2 className="text-lead font-semibold text-foreground">
                    {title}
                </h2>
                <p className="text-body text-muted-foreground">{body}</p>
                <div className="flex justify-between gap-3 pt-1">
                    <Button variant="ghost" onClick={onSkip}>
                        Skip tour
                    </Button>
                    <Button ref={nextRef} onClick={onNext}>
                        {last ? 'Done' : 'Next'}
                    </Button>
                </div>
            </PopoverContent>
        </Popover>
    );
}
