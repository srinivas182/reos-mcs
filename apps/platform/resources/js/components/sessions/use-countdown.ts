import { useEffect, useState } from 'react';

/** Seconds until a moment, ticking every second (never below zero). */
export function useCountdown(until: string): number {
    const target = new Date(until).getTime();
    const [left, setLeft] = useState(() =>
        Math.max(0, Math.round((target - Date.now()) / 1000)),
    );

    useEffect(() => {
        const tick = () =>
            setLeft(Math.max(0, Math.round((target - Date.now()) / 1000)));
        const timer = window.setInterval(tick, 1000);

        return () => window.clearInterval(timer);
    }, [target]);

    return left;
}

/** 2700 → "45:00", 59 → "0:59". */
export function clock(seconds: number): string {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;

    return `${m}:${String(s).padStart(2, '0')}`;
}
