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
