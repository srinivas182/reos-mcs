import { Calculator as CalculatorIcon } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';

type Op = '+' | '−' | '×' | '÷';

/** Applies one operation; division by zero gives an error rather than Infinity. */
export function apply(a: number, op: Op, b: number): number | null {
    switch (op) {
        case '+':
            return a + b;
        case '−':
            return a - b;
        case '×':
            return a * b;
        default:
            return b === 0 ? null : a / b;
    }
}

/** Up to 10 significant digits, no trailing zeros. */
export function show(value: number): string {
    return Number.parseFloat(value.toPrecision(10)).toString();
}

/**
 * The on-screen calculator (candidate screen 41, A9.1), like the test
 * centre's: four operations, percent, memory (M+, M−, MR, MC), clear.
 * Keyboard digits and operators work while it's open.
 */
export function Calculator() {
    const { t } = useTranslation();
    const [display, setDisplay] = useState('0');
    const [stored, setStored] = useState<number | null>(null);
    const [op, setOp] = useState<Op | null>(null);
    const [fresh, setFresh] = useState(true);
    const [memory, setMemory] = useState(0);
    const value = Number.parseFloat(display);

    const digit = (d: string) => {
        if (d === '.' && display.includes('.') && !fresh) return;
        setDisplay(
            fresh
                ? d === '.'
                    ? '0.'
                    : d
                : display === '0' && d !== '.'
                  ? d
                  : display + d,
        );
        setFresh(false);
    };

    const operate = (next: Op | null) => {
        let result: number | null = value;
        if (op !== null && stored !== null && !fresh) {
            result = apply(stored, op, value);
        } else if (stored !== null && fresh) {
            result = stored;
        }
        if (result === null) {
            setDisplay(t('practice.calculator.error'));
            setStored(null);
            setOp(null);
        } else {
            setDisplay(show(result));
            setStored(next === null ? null : result);
            setOp(next);
        }
        setFresh(true);
    };

    // "a × b %" is b% of a; "a + b %" adds b% of a; on its own, b ÷ 100. It completes the sum.
    const percent = () => {
        let result: number | null = value / 100;
        if (stored !== null && op !== null) {
            result =
                op === '×'
                    ? (stored * value) / 100
                    : op === '÷'
                      ? value === 0
                          ? null
                          : (stored / value) * 100
                      : op === '+'
                        ? stored + (stored * value) / 100
                        : stored - (stored * value) / 100;
        }
        setDisplay(
            result === null ? t('practice.calculator.error') : show(result),
        );
        setStored(null);
        setOp(null);
        setFresh(true);
    };

    const keys: {
        label: string;
        aria?: string;
        run: () => void;
        wide?: boolean;
    }[] = [
        {
            label: 'MC',
            aria: `MC, ${t('practice.calculator.mc')}`,
            run: () => setMemory(0),
        },
        {
            label: 'MR',
            aria: `MR, ${t('practice.calculator.mr')}`,
            run: () => {
                setDisplay(show(memory));
                setFresh(true);
            },
        },
        {
            label: 'M+',
            aria: `M+, ${t('practice.calculator.mPlus')}`,
            run: () => setMemory(memory + value),
        },
        {
            label: 'M−',
            aria: `M−, ${t('practice.calculator.mMinus')}`,
            run: () => setMemory(memory - value),
        },
        {
            label: 'C',
            aria: t('practice.calculator.clear'),
            run: () => {
                setDisplay('0');
                setStored(null);
                setOp(null);
                setFresh(true);
            },
        },
        {
            label: '%',
            aria: t('practice.calculator.percent'),
            run: percent,
        },
        { label: '÷', run: () => operate('÷') },
        { label: '×', run: () => operate('×') },
        ...['7', '8', '9'].map((d) => ({ label: d, run: () => digit(d) })),
        { label: '−', run: () => operate('−') },
        ...['4', '5', '6'].map((d) => ({ label: d, run: () => digit(d) })),
        { label: '+', run: () => operate('+') },
        ...['1', '2', '3'].map((d) => ({ label: d, run: () => digit(d) })),
        {
            label: '=',
            aria: t('practice.calculator.equals'),
            run: () => operate(null),
        },
        { label: '0', run: () => digit('0'), wide: true },
        {
            label: '.',
            aria: t('practice.calculator.point'),
            run: () => digit('.'),
        },
    ];

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button variant="outline">
                    <CalculatorIcon aria-hidden />
                    {t('practice.calculator.title')}
                </Button>
            </PopoverTrigger>
            <PopoverContent
                className="w-72"
                aria-label={t('practice.calculator.title')}
            >
                <output
                    aria-live="polite"
                    className="mb-3 block rounded-reos-md bg-muted px-3 py-2 text-right text-2xl font-semibold tabular-nums"
                >
                    {memory !== 0 ? (
                        <span className="float-left text-sm text-muted-foreground">
                            M
                        </span>
                    ) : null}
                    {display}
                </output>
                <div className="grid grid-cols-4 gap-2">
                    {keys.map((key) => (
                        <Button
                            key={key.label}
                            type="button"
                            variant="outline"
                            aria-label={key.aria}
                            className={key.wide ? 'col-span-2' : undefined}
                            onClick={key.run}
                        >
                            {key.label}
                        </Button>
                    ))}
                </div>
            </PopoverContent>
        </Popover>
    );
}
