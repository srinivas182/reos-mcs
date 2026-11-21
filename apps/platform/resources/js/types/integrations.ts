/** Settings › Integrations (Sprint 2.4). Shapes match App\Http\Resources\Integrations. */

export type IntegrationMode = 'off' | 'simulator' | 'sandbox' | 'live';
export type CredentialEnvironment = 'sandbox' | 'live';
export type ApprovalStatus =
    | 'not_started'
    | 'applied'
    | 'in_review'
    | 'approved'
    | 'blocked'
    | 'not_needed';
export type IntegrationCategory =
    | 'identity'
    | 'calendar_email'
    | 'voice'
    | 'payments'
    | 'notifications'
    | 'video'
    | 'ai'
    | 'crm'
    | 'transactions'
    | 'esignature'
    | 'mls'
    | 'lookups'
    | 'social'
    | 'maps'
    | 'accounting'
    | 'association'
    | 'operations'
    | 'platform'
    | 'regulatory'
    | 'meetings'
    | 'leads'
    | 'contacts';

/** What people see for a tool (B3.2, web journey screen 64). */
export type CatalogueStatus =
    | 'enabled'
    | 'beta'
    | 'coming_soon'
    | 'paused'
    | 'retired';

export type CatalogueSummary = {
    status: CatalogueStatus;
    /** Null: everyone. */
    states: string[] | null;
    roles: string[] | null;
    plans: string[] | null;
    connected: number;
    demand: number;
};

export type CatalogueDetail = {
    /** The super admin's choice; null means the register's default. */
    status: CatalogueStatus | null;
    effective: CatalogueStatus;
    default: CatalogueStatus;
    retires_on: string | null;
    states: string[] | null;
    roles: string[] | null;
    plans: string[] | null;
    beta_organisations: number[];
    tenants_may_hide: boolean;
    tenants_may_require: boolean;
    show_coming_soon: boolean;
    /** The connector exists; until then people see "Coming soon". */
    built: boolean;
    summary: {
        auth: 'api_key' | 'oauth2' | 'aws' | 'none';
        accounts: 'platform' | 'user' | 'tenant';
        /** Requests a minute, shared fairly; null until the connector exists. */
        rate_limit: number | null;
    };
    demand: number;
    options: {
        states: { code: string; name: string }[];
        roles: string[];
        organisations: { id: number; name: string }[];
    };
};

export type IntegrationHealth = {
    connected: number;
    reconnect: number;
    failing: number;
    runs: number;
    success: number | null;
};

export type IntegrationSummary = {
    id: string;
    name: string;
    purpose: string;
    kind: 'integration' | 'platform_service';
    category: IntegrationCategory;
    approval: ApprovalStatus;
    personal_data: boolean;
    needs_keys: boolean;
    mode: IntegrationMode;
    /** Set when health checks paused it; features then treat it as Off. */
    paused_at: string | null;
    keys: Record<CredentialEnvironment, boolean>;
    /** Null for platform services, which people never connect. */
    catalogue?: CatalogueSummary | null;
};

export type CheckOutcome = 'passed' | 'failed' | 'unverified';

export type CheckMessage =
    | 'integrations.checks.passed'
    | 'integrations.checks.rejected'
    | 'integrations.checks.wrongEnvironment'
    | 'integrations.checks.unreachable'
    | 'integrations.checks.vendorError'
    | 'integrations.checks.invalidFormat'
    | 'integrations.checks.missingKeys'
    | 'integrations.checks.unexpected'
    | 'integrations.checks.noAutomaticCheck'
    | 'integrations.checks.formatOnly'
    | 'integrations.checks.publicData';

export type IntegrationCheck = {
    outcome: CheckOutcome;
    message: CheckMessage;
    parameters: Record<string, string | number>;
    trigger: 'manual' | 'scheduled';
    checked_by: string | null;
    duration_ms: number | null;
    created_at: string;
};

export type IntegrationFieldKind =
    | 'text'
    | 'url'
    | 'email'
    | 'phone'
    | 'multiline';

export type IntegrationField = {
    key: string;
    secret: boolean;
    required: boolean;
    kind: IntegrationFieldKind;
    default: string | null;
};

export type SavedCredential = {
    saved: boolean;
    value: string | null;
    last4: string | null;
};

export type ModeReason =
    | 'integrations.mode.needsSandboxKeys'
    | 'integrations.mode.needsLiveKeys'
    | 'integrations.mode.needsLiveTest'
    | 'integrations.mode.liveTestFailed'
    | 'integrations.mode.noTestSystem';

export type IntegrationDetail = IntegrationSummary & {
    sandbox_notes: string;
    build_sprint: number;
    auth: 'api_key' | 'oauth2' | 'aws' | 'none';
    accounts: 'platform' | 'user' | 'tenant';
    has_test_system: boolean;
    redirect_url: string | null;
    fields: IntegrationField[];
    credentials: Record<CredentialEnvironment, Record<string, SavedCredential>>;
    checks: Record<CredentialEnvironment, IntegrationCheck | null>;
    modes: {
        value: IntegrationMode;
        available: boolean;
        reason: ModeReason | null;
    }[];
};

export type AuditActivity = {
    id: number;
    action:
        | 'integration.credentials_updated'
        | 'integration.credentials_removed'
        | 'integration.mode_changed'
        | 'integration.checked'
        | 'integration.resumed'
        | 'integration.paused';
    actor: string | null;
    properties: {
        environment?: CredentialEnvironment;
        fields?: string[];
        from?: IntegrationMode;
        to?: IntegrationMode;
        outcome?: CheckOutcome;
        failures?: number;
    };
    created_at: string;
};
