import type {
    TenantDetail,
    TenantFeature,
    TenantLimits,
    TenantOptions,
} from '@/types';

/** Realistic tenants for tests and stories (illustrative demo data). */
export const loneStarLimits: TenantLimits = {
    seats: 142,
    payer: 'organisation',
    credits_per_seat: 300,
    credits_pooled: true,
    candidate_pool: 5000,
    rate_limit: 600,
    queue_weight: 'standard',
    log_retention_years: 7,
    deleted_user_days: 30,
};

export const loneStarFeatures: TenantFeature[] = [
    { key: 'virtual_broker', on: true, default: true, availability: 'default' },
    {
        key: 'contract_review',
        on: true,
        default: true,
        availability: 'default',
    },
    {
        key: 'provider_marketplace',
        on: true,
        default: true,
        availability: 'default',
    },
    {
        key: 'recruiting_agents',
        on: false,
        default: false,
        availability: 'beta',
    },
    {
        key: 'ai_video_lessons',
        on: false,
        default: false,
        availability: 'add_on',
    },
    { key: 'spanish_ui', on: true, default: true, availability: 'default' },
];

export const loneStar: TenantDetail = {
    id: 3,
    name: 'Lone Star Realty',
    type: 'brokerage',
    parent: null,
    states: ['TX'],
    plan: 'brokerage',
    seats: 142,
    members: 131,
    domain: 'lone-star-realty.reos.com',
    cell: 'cell-1',
    status: 'active',
    trial_days_left: null,
    slug: 'lone-star-realty',
    region: 'us-east-2',
    created_at: '2026-09-30T10:00:00Z',
    trial_ends_at: null,
    status_reason: null,
    owner: { name: 'Linda Park', email: 'linda.park@lonestarrealty.example' },
    last_active_at: new Date(Date.now() - 20 * 60_000).toISOString(),
    connection_problems: 0,
    limits: loneStarLimits,
    default_limits: { ...loneStarLimits, seats: 25, candidate_pool: 0 },
    features: loneStarFeatures,
    children: [{ id: 9, name: 'Okoye Team', type: 'team' }],
    hidden_tools: [{ id: 'zoom', name: 'Zoom' }],
    connections: [],
};

export const tenantOptions: TenantOptions = {
    types: ['association', 'brokerage', 'school', 'provider_business', 'team'],
    statuses: ['active', 'trial', 'suspended', 'archived'],
    plans: [
        'enterprise',
        'association',
        'brokerage',
        'school',
        'partner',
        'inherits',
    ],
    states: [{ code: 'TX', name: 'Texas' }],
    brokerages: [{ id: 3, name: 'Lone Star Realty' }],
};
