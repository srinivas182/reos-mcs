export type TenantType =
    | 'association'
    | 'brokerage'
    | 'school'
    | 'provider_business'
    | 'team';

export type TenantStatus = 'active' | 'trial' | 'suspended' | 'archived';

export type TenantPlan =
    | 'enterprise'
    | 'association'
    | 'brokerage'
    | 'school'
    | 'partner'
    | 'inherits';

/** A row on Tenants (screen 59). */
export type TenantRow = {
    id: number;
    name: string;
    type: TenantType;
    parent: string | null;
    states: string[];
    plan: TenantPlan;
    seats: number | null;
    members: number;
    domain: string | null;
    cell: string;
    status: TenantStatus;
    trial_days_left: number | null;
};

export type TenantLimits = {
    seats: number | null;
    payer: 'organisation' | 'member';
    credits_per_seat: number;
    credits_pooled: boolean;
    candidate_pool: number;
    rate_limit: number;
    queue_weight: 'low' | 'standard' | 'high';
    log_retention_years: number;
    deleted_user_days: number;
};

export type TenantFeatureKey =
    | 'virtual_broker'
    | 'contract_review'
    | 'provider_marketplace'
    | 'recruiting_agents'
    | 'ai_video_lessons'
    | 'spanish_ui';

export type TenantFeature = {
    key: TenantFeatureKey;
    on: boolean;
    default: boolean;
    availability: 'default' | 'beta' | 'add_on';
};

/** One tenant (screen 60). */
export type TenantDetail = TenantRow & {
    slug: string;
    region: string;
    created_at: string | null;
    trial_ends_at: string | null;
    status_reason: string | null;
    owner: { name: string; email: string } | null;
    last_active_at: string | null;
    connection_problems: number;
    limits: TenantLimits;
    default_limits: TenantLimits;
    features: TenantFeature[];
    children: { id: number; name: string; type: TenantType }[];
    hidden_tools: { id: string; name: string }[];
    connections: {
        id: number;
        name: string;
        account: string | null;
        status: 'active' | 'reconnect_needed' | 'failing';
    }[];
};

export type TenantOptions = {
    types: TenantType[];
    statuses: TenantStatus[];
    plans: TenantPlan[];
    states: { code: string; name: string }[];
    brokerages: { id: number; name: string }[];
};

export type TenantFilters = {
    type: TenantType | null;
    status: TenantStatus | null;
    state: string | null;
    search: string | null;
};

/** An entry on the tenant's Audit tab. */
export type TenantAuditEvent = {
    id: number;
    action: string;
    actor: string | null;
    properties: {
        changed?: string[];
        from?: string;
        to?: string;
        reason?: string | null;
    } | null;
    created_at: string;
};
