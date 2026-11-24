/** The virtual broker's shapes (A12.1), as the API and pages send them. */
export type PolicySource = {
    kind: 'manual' | 'faq' | 'rule';
    title: string;
    citation: string | null;
    page: number | null;
    url: string | null;
    effective_from: string | null;
    excerpt: string;
};

export type BrokerQuestion = {
    id: string;
    question: string;
    answer: string;
    confidence: 'high' | 'medium' | 'low';
    sources: PolicySource[];
    status: 'answered' | 'waiting' | 'confirmed' | 'corrected';
    reason: 'unclear' | 'money' | 'legal' | 'asked' | null;
    escalated_at: string | null;
    due_at: string | null;
    broker_answer: string | null;
    broker: string | null;
    answered_at: string | null;
    added_to_knowledge: boolean;
    agent: string;
    deal: { id: number; uuid: string; property: string } | null;
    created_at: string | null;
};

export type AgentVirtualBroker = {
    brokerage: string;
    manual: boolean;
    reply_hours: number;
    questions: BrokerQuestion[];
};

/** Topics PolicyKnowledge finds in a manual. */
export type PolicyTopic =
    | 'advertising'
    | 'splits'
    | 'escrow'
    | 'coordinators'
    | 'leads'
    | 'forms'
    | 'supervision'
    | 'fair_housing';

export type PolicyConflict = {
    rule: string;
    citation: string;
    url: string | null;
    page: number | null;
    excerpt: string;
    rule_text: string;
};

export type BrokerVirtualBroker = {
    brokerage: string;
    manual: {
        id: string;
        version: number;
        file_name: string;
        pages: number;
        passages: number;
        topics: PolicyTopic[];
        uploaded_at: string | null;
        uploaded_by: string | null;
    } | null;
    versions: {
        id: string;
        version: number;
        file_name: string;
        uploaded_at: string | null;
        current: boolean;
    }[];
    conflicts: PolicyConflict[];
    faq: {
        id: number;
        question: string;
        answer: string;
        added_at: string | null;
    }[];
    settings: {
        answerer_id: number | null;
        reply_hours: number;
        brokers: { id: number; name: string }[];
    };
    waiting: BrokerQuestion[];
    answered: BrokerQuestion[];
    stats: {
        answered_by_ai: number;
        sent_to_broker: number;
        reply_minutes: number | null;
    };
};
