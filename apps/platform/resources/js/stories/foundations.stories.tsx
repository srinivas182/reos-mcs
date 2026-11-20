import type { Meta, StoryObj } from '@storybook/react-vite';
import {
    fontSize,
    lineHeight,
    minTouchTarget,
    radius,
    themes,
} from '@reos/tokens';

/**
 * The design tokens behind every component (packages/tokens). Switch Theme and
 * Text size in the toolbar to see them change.
 */
const meta = {
    title: 'Foundations/Tokens',
    parameters: { layout: 'padded' },
} satisfies Meta;

export default meta;
type Story = StoryObj<typeof meta>;

const kebab = (s: string): string =>
    s.replace(/[A-Z]/g, (c) => `-${c.toLowerCase()}`);

export const Colours: Story = {
    render: () => (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {Object.keys(themes.light).map((name) => (
                <div
                    key={name}
                    className="flex items-center gap-3 rounded-reos-md border border-border bg-card p-3"
                >
                    <span
                        aria-hidden
                        className="size-12 shrink-0 rounded-reos-sm border border-border"
                        style={{ background: `var(--reos-${kebab(name)})` }}
                    />
                    <span>
                        <span className="block font-semibold text-foreground">
                            {name}
                        </span>
                        <code className="text-caption text-muted-foreground">
                            --reos-{kebab(name)}
                        </code>
                    </span>
                </div>
            ))}
        </div>
    ),
};

export const Type: Story = {
    render: () => (
        <div className="space-y-4">
            {(Object.keys(fontSize) as Array<keyof typeof fontSize>).map(
                (size) => (
                    <p
                        key={size}
                        className="text-foreground"
                        style={{
                            fontSize: `${fontSize[size] / 16}rem`,
                            lineHeight: `${lineHeight[size] / 16}rem`,
                        }}
                    >
                        {size} · {fontSize[size]}px: Find a mentor, finish your
                        CE, close more deals.
                    </p>
                ),
            )}
        </div>
    ),
};

export const ShapeAndTouch: Story = {
    name: 'Shape and touch targets',
    render: () => (
        <div className="flex flex-wrap items-end gap-4">
            {Object.entries(radius).map(([name, value]) => (
                <div key={name} className="text-center">
                    <div
                        className="mb-2 size-20 border-2 border-primary bg-accent"
                        style={{ borderRadius: value === 999 ? 999 : value }}
                    />
                    <span className="text-caption text-muted-foreground">
                        radius {name}
                    </span>
                </div>
            ))}
            <div className="text-center">
                <div
                    className="mb-2 border-2 border-dashed border-primary"
                    style={{ width: minTouchTarget, height: minTouchTarget }}
                />
                <span className="text-caption text-muted-foreground">
                    touch {minTouchTarget}px
                </span>
            </div>
        </div>
    ),
};
