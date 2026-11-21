import { usePage } from '@inertiajs/react';
import type { BrandProps } from '@/types';

/**
 * The tenant's colour on buttons, links, focus rings and the active menu
 * item (A4.2a). Light theme only: the dark and high-contrast themes keep
 * REOS's tested colours, and the logo and name still carry the brand.
 */
export function BrandStyle({
    palette,
}: {
    palette: NonNullable<BrandProps['palette']> | null;
}) {
    if (!palette) {
        return null;
    }

    const css = `html:not(.dark):not([data-theme='dark']):not([data-theme='contrast']){--reos-action:${palette.action};--reos-action-pressed:${palette.pressed};--reos-action-tint:${palette.tint};--reos-focus:${palette.action};--reos-nav-active:${palette.action};}`;

    return <style data-brand>{css}</style>;
}

/** The brand of the page being shown (shared prop). */
export function PageBrandStyle() {
    const { brand } = usePage().props;

    return <BrandStyle palette={brand?.palette ?? null} />;
}
