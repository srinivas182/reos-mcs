import { Pause, Play } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';

/** In for 4, hold for 4, out for 6: about 8 rounds in two minutes. */
const PHASES = [
    { key: 'in', seconds: 4 },
    { key: 'hold', seconds: 4 },
    { key: 'out', seconds: 6 },
] as const;

const TOTAL = 120;

/**
 * The two-minute calm-down (exam-day kit, screen 14): paced breathing,
 * spoken by the device's own voice where it has one, and shown on screen.
 */
export function CalmDown() {
    const { t, i18n } = useTranslation();
    const [left, setLeft] = useState<number | null>(null);
    const timer = useRef<ReturnType<typeof setInterval> | undefined>(undefined);
    const elapsed = left === null ? 0 : TOTAL - left;
    const cycle = PHASES.reduce((s, p) => s + p.seconds, 0);
    const at = elapsed % cycle;
    const phase =
        at < PHASES[0].seconds
            ? PHASES[0]
            : at < PHASES[0].seconds + PHASES[1].seconds
              ? PHASES[1]
              : PHASES[2];
    const label = t(`licence.calm.${phase.key}`);
    const running = left !== null;

    useEffect(() => () => clearInterval(timer.current), []);

    // Say each phase as it starts, when the browser can speak.
    useEffect(() => {
        if (
            left === null ||
            typeof window === 'undefined' ||
            !('speechSynthesis' in window)
        ) {
            return;
        }

        const utterance = new SpeechSynthesisUtterance(label);
        utterance.lang = i18n.language === 'es' ? 'es-US' : 'en-US';
        utterance.rate = 0.85;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }, [label, running, i18n.language]);

    const start = () => {
        setLeft(TOTAL);
        clearInterval(timer.current);
        timer.current = setInterval(
            () =>
                setLeft((s) => {
                    if (s === null || s <= 1) {
                        clearInterval(timer.current);

                        return null;
                    }

                    return s - 1;
                }),
            1000,
        );
    };
    const stop = () => {
        clearInterval(timer.current);
        setLeft(null);
        if (typeof window !== 'undefined' && 'speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
    };

    return (
        <section
            aria-labelledby="calm-down"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="calm-down" className="text-lg font-semibold">
                {t('licence.calm.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('licence.calm.body')}
            </p>
            {left !== null ? (
                <p aria-live="polite" className="text-title font-semibold">
                    {label}
                    <span className="ml-3 text-base font-normal text-muted-foreground tabular-nums">
                        {t('licence.calm.left', {
                            time: `${Math.floor(left / 60)}:${String(left % 60).padStart(2, '0')}`,
                        })}
                    </span>
                </p>
            ) : null}
            <Button
                variant={left === null ? 'default' : 'outline'}
                onClick={left === null ? start : stop}
            >
                {left === null ? <Play aria-hidden /> : <Pause aria-hidden />}
                {left === null
                    ? t('licence.calm.start')
                    : t('licence.calm.stop')}
            </Button>
        </section>
    );
}
