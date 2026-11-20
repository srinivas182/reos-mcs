import { Contrast, Monitor, Moon, Sun } from 'lucide-react';
import { ChoiceTiles } from '@/components/choice-tiles';
import type { ChoiceTile } from '@/components/choice-tiles';
import type { Appearance, TextSize } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const themeOptions: readonly ChoiceTile<Appearance>[] = [
    {
        value: 'system',
        label: 'Match my device',
        description: 'Light or dark, as your device is set',
        icon: Monitor,
    },
    {
        value: 'light',
        label: 'Light',
        description: 'Bright and clean',
        icon: Sun,
    },
    {
        value: 'dark',
        label: 'Dark',
        description: 'Easier on the eyes at night',
        icon: Moon,
    },
    {
        value: 'contrast',
        label: 'Calm high contrast',
        description: 'Strongest contrast, for low vision or sunlight',
        icon: Contrast,
    },
];

const sample = (scale: string) => (
    <span
        aria-hidden
        className="mt-2 font-semibold text-foreground"
        style={{ fontSize: `calc(1rem * ${scale})` }}
    >
        Aa
    </span>
);

/**
 * Sizes are previewed relative to the current size, so the tiles stay readable
 * whichever size is active.
 */
const sizeOptions: readonly ChoiceTile<TextSize>[] = [
    {
        value: 'standard',
        label: 'Standard',
        description: 'Our normal text size',
        preview: sample('1'),
    },
    {
        value: 'large',
        label: 'Large',
        description: '15% larger everywhere',
        preview: sample('1.15'),
    },
    {
        value: 'largest',
        label: 'Largest',
        description: '30% larger everywhere',
        preview: sample('1.3'),
    },
];

/** Theme and text-size choices. Changes apply instantly and are saved to the account. */
export default function AppearanceSettings() {
    const { appearance, textSize, updateAppearance, updateTextSize } =
        useAppearance();

    return (
        <div className="space-y-8">
            <ChoiceTiles
                label="Theme"
                value={appearance}
                options={themeOptions}
                onChange={updateAppearance}
            />
            <ChoiceTiles
                label="Text size"
                value={textSize}
                options={sizeOptions}
                onChange={updateTextSize}
            />
            <p className="text-caption text-muted-foreground">
                Your choices are saved to your account, so they follow you to
                every device. Your phone or computer&apos;s own text setting
                still applies on top.
            </p>
        </div>
    );
}
