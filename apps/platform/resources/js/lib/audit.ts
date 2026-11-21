import type { TFunction } from 'i18next';

const text = (value: unknown): string => {
    if (value === null || value === undefined) {
        return '—';
    }

    return typeof value === 'string' ||
        typeof value === 'number' ||
        typeof value === 'boolean'
        ? String(value)
        : JSON.stringify(value);
};

/** "Changed status: Active → Suspended" and the like, in the reader's language. */
export function auditLabel(
    t: TFunction,
    action: string,
    before: unknown = null,
    after: unknown = null,
): string {
    const scalar = (value: unknown) =>
        typeof value === 'string' || typeof value === 'number'
            ? String(value)
            : '';

    return t(`tenants.audit.actions.${action}`, {
        defaultValue: action,
        from: scalar(before),
        to: scalar(after),
        path: '',
    });
}

/**
 * What changed, compactly: "Active → Suspended", or field by field for
 * objects ("plan: brokerage → enterprise; seats: 25 → 142").
 */
export function describeChange(before: unknown, after: unknown): string {
    if (before === null && after === null) {
        return '';
    }

    const isObject = (value: unknown): value is Record<string, unknown> =>
        typeof value === 'object' && value !== null && !Array.isArray(value);

    if (isObject(before) || isObject(after)) {
        const was = isObject(before) ? before : {};
        const now = isObject(after) ? after : {};
        const keys = [...new Set([...Object.keys(was), ...Object.keys(now)])];

        return keys
            .filter((key) => text(was[key]) !== text(now[key]))
            .map((key) => `${key}: ${text(was[key])} → ${text(now[key])}`)
            .join('; ');
    }

    return `${text(before)} → ${text(after)}`;
}
