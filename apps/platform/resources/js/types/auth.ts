export type User = {
    id: number;
    /** Null until given: people who sign up with a phone give it in setup. */
    name: string | null;
    email: string | null;
    phone?: string | null;
    avatar?: string;
    email_verified_at: string | null;
    /* @chisel-2fa */
    two_factor_enabled?: boolean;
    /* @end-chisel-2fa */
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type RoleName =
    | 'candidate'
    | 'agent'
    | 'broker'
    | 'mentor'
    | 'instructor'
    | 'school_admin'
    | 'provider'
    | 'association_admin'
    | 'super_admin';

export type OrganisationType =
    | 'platform'
    | 'brokerage'
    | 'school'
    | 'association'
    | 'provider_business'
    | 'team';

/** One role a person holds, in one organisation. */
export type Membership = {
    role: RoleName;
    organisation: { id: number; name: string; type: OrganisationType };
    /** Admin roles need two-step before they work. */
    admin: boolean;
};

/** Roles held, the one in use on this browser, and what it allows (A3.2). */
export type Roles = {
    held: Membership[];
    active: { role: RoleName; organisationId: number } | null;
    permissions: string[];
    /** An authenticator app or a passkey is set up. */
    twoStepEnabled: boolean;
    /** The active role is an admin role and two-step isn't set up yet. */
    twoStepRequired: boolean;
};

export type Auth = {
    user: User;
    hasPassword: boolean;
    /** What the signed-in person may do (from the server's gates). */
    can: {
        manageIntegrations: boolean;
        managePeople: boolean;
    };
    roles: Roles | null;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

/* @chisel-2fa */
export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
/* @end-chisel-2fa */
