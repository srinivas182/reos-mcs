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
    | 'regulatory';

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
    keys: Record<CredentialEnvironment, boolean>;
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
        | 'integration.mode_changed';
    actor: string | null;
    properties: {
        environment?: CredentialEnvironment;
        fields?: string[];
        from?: IntegrationMode;
        to?: IntegrationMode;
    };
    created_at: string;
};
