import type { CatalogueItem, IntegrationCategory } from '@/types';

/** The website's tool types (web journey screen 19), in the journey's order. */
export const WEB_TABS = [
    'crm',
    'transactions',
    'calendar_email',
    'leads',
    'social',
    'mls',
    'esignature',
    'more',
] as const;

export type WebTab = (typeof WEB_TABS)[number];

/** Which tab a tool sits under. Meeting tools sit with the calendars; rare types go under More. */
export function webTab(category: IntegrationCategory): WebTab {
    if (category === 'meetings') {
        return 'calendar_email';
    }

    return (WEB_TABS as readonly string[]).includes(category)
        ? (category as WebTab)
        : 'more';
}

const ORDER: Record<CatalogueItem['action'], number> = {
    connect: 1,
    join_beta: 2,
    ask_broker: 3,
    notify: 4,
    none: 5,
};

/** Connected tools first, then what can be connected, beta, coming soon and the rest. */
export function byNextStep(a: CatalogueItem, b: CatalogueItem): number {
    const rank = (tool: CatalogueItem) =>
        tool.connected > 0 ? 0 : ORDER[tool.action];

    return rank(a) - rank(b);
}
