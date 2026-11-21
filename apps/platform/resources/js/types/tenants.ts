import type { RoleName } from '@/types/auth';

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

export type SupportAccessStatus =
    | 'pending'
    | 'approved'
    | 'declined'
    | 'active'
    | 'ended'
    | 'expired'
    | 'revoked';

/** Support sign-in for a tenant (A4.1c), as the super admin sees it. */
export type TenantSupport = {
    can_ask: boolean;
    latest: {
        status: SupportAccessStatus;
        mine: boolean;
        requester: string;
        decided_by: string | null;
        requested_at: string | null;
        ends_at: string | null;
    } | null;
};

/** A support sign-in request, as the tenant owner sees it. */
export type SupportGrant = {
    id: number;
    tenant: string;
    requester: string;
    reason: string;
    status: SupportAccessStatus;
    requested_at: string | null;
    ends_at: string | null;
    decided_by: string | null;
    minutes: number;
    answer_hours: number;
};

/** A tenant's own domain on screens 41 and 57 (A4.2b). */
export type TenantDomain = {
    hostname: string;
    status: 'pending' | 'active' | 'failed';
    ssl: 'pending' | 'active';
    live: boolean;
    records: { type: string; name: string; value: string; verified: boolean }[];
    error: string | null;
    last_checked_at: string | null;
    verified_at: string | null;
};

/** Single sign-on on screen 57 (A4.2c). */
export type SsoStatus = 'draft' | 'tested' | 'on';
export type SsoProtocol = 'saml' | 'oidc';

export type SsoScreen = {
    settings: {
        protocol: SsoProtocol;
        status: SsoStatus;
        issuer?: string;
        client_id?: string;
        entity_id?: string;
        sso_url?: string;
        metadata_url?: string | null;
        has_secret: boolean;
        certificate_expires: string | null;
        test_provider: boolean;
        last_test: {
            at: string;
            passed: boolean;
            detail: string | null;
        } | null;
        enabled_at: string | null;
    } | null;
    service_provider: {
        entity_id: string;
        acs_url: string;
        metadata_url: string;
        redirect_uri: string;
    };
    members: number;
    test_provider_available: boolean;
};

/** What the invite sheet needs (A4.3a, screens 32 and 41). */
export type InviteOptions = {
    roles: { value: RoleName; label: string; seat: boolean }[];
    teams: { id: number; name: string }[];
    can_text: boolean;
    seats: {
        role: RoleName | null;
        limit: number | null;
        used: number;
        pending: number;
        left: number | null;
        payer: 'organisation' | 'member';
    };
    locale: 'en' | 'es';
};

export type InviteResult = {
    sent: number;
    skipped: {
        contact: string;
        reason: 'duplicate' | 'member' | 'invited' | 'noText' | 'invalid';
    }[];
};

export type AgentRow = {
    id: number;
    name: string;
    email: string | null;
    team: string | null;
    last_active: string | null;
};

export type InvitationRow = {
    id: number;
    contact: string;
    channel: 'email' | 'text';
    name: string | null;
    role: RoleName;
    team: string | null;
    state: 'pending' | 'expired';
    sent_at: string | null;
    expires_at: string;
    reminded: boolean;
    invited_by: string | null;
};
