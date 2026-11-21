/**
 * The same contrast rule the server enforces for brand colours (A4.2a):
 * white text on the colour must reach 4.5:1 (WCAG AA).
 */
export const MIN_CONTRAST = 4.5;

const rgb = (hex: string): [number, number, number] => [
    parseInt(hex.slice(1, 3), 16),
    parseInt(hex.slice(3, 5), 16),
    parseInt(hex.slice(5, 7), 16),
];

export function normaliseHex(value: string): string | null {
    const match = /^#?([0-9a-f]{6})$/i.exec(value.trim());

    return match ? `#${match[1]!.toUpperCase()}` : null;
}

export function contrastWithWhite(hex: string): number {
    const channel = (value: number) => {
        const c = value / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const [r, g, b] = rgb(hex);
    const luminance =
        0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b);

    return 1.05 / (luminance + 0.05);
}

function mix(hex: string, other: string, share: number): string {
    const a = rgb(hex);
    const b = rgb(other);

    return `#${a
        .map((v, i) =>
            Math.round(v + (b[i]! - v) * share)
                .toString(16)
                .padStart(2, '0'),
        )
        .join('')
        .toUpperCase()}`;
}

/** The nearest darker shade white text is readable on. */
export function readableShade(hex: string): string {
    let colour = hex;

    for (let i = 0; i < 40 && contrastWithWhite(colour) < MIN_CONTRAST; i++) {
        colour = mix(colour, '#000000', 0.05);
    }

    return colour;
}

export function palette(hex: string) {
    return {
        action: hex,
        pressed: mix(hex, '#000000', 0.18),
        tint: mix(hex, '#FFFFFF', 0.9),
    };
}
