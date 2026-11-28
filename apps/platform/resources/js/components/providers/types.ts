export type FieldType = 'text' | 'list' | 'yes_no';

export type JoinField = {
    key: string;
    label: string;
    type: FieldType;
    options: string[];
};

export type CategoryCard = {
    key: string;
    name: string;
    description: string | null;
    licence: {
        kind: 'nmls' | 'trec_inspector' | 'tdi' | 'business';
        label: string | null;
    };
    fields: JoinField[];
    areas_on_pro: number;
    prices: {
        basic: number | null;
        pro: number | null;
        premium: number | null;
    };
};

export type Answer = string | boolean | string[];

export type Joining = {
    category: CategoryCard | null;
    fields: Record<string, Answer>;
    missing: string[];
};

export type JoinRoutes = {
    request: string;
    category: string;
    fields: string;
};

export type StepKey =
    | 'welcome'
    | 'category'
    | 'licence'
    | 'areas'
    | 'plan'
    | 'rules';

export type LicenceRow = {
    id: number;
    kind: string;
    number: string;
    status: string;
    name_on_record: string | null;
    licence_type: string | null;
    expires_on: string | null;
    checked_via: string | null;
    review_note: string | null;
    consumer_url: string | null;
};

export type AreaRow = {
    id: number;
    kind: 'city' | 'zip' | 'radius';
    city: string | null;
    postal_code: string | null;
    radius_miles: number | null;
    agents: number;
    providers: number;
};

export type Tier = 'basic' | 'pro' | 'premium';

export type Onboarding = {
    status: string;
    hidden_reason: string | null;
    steps: { key: StepKey; done: boolean }[];
    done: number;
    joining: Joining;
    licence: {
        kind: string;
        licences: LicenceRow[];
        business: { name: string | null; address: string | null } | null;
        verified: boolean;
    };
    areas: AreaRow[];
    plans: { tier: Tier; monthly: number; areas: number }[];
    tier: string | null;
    rules: {
        version: string;
        keys: string[];
        accepted_at: string | null;
        current: boolean;
    };
};

export type OnboardingRoutes = JoinRoutes & {
    status: string;
    welcome: string;
    business: string;
    licence: string;
    areas: string;
    area: (id: number) => string;
    plan: string;
    rules: string;
};
