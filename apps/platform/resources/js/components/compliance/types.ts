/** The compliance engine's answer (B13.1), as the check and fix routes send it. */
export type ComplianceIssue = {
    code:
        | 'broker'
        | 'broker_size'
        | 'team_name'
        | 'links'
        | 'claims'
        | 'title'
        | 'fair_housing'
        | 'unclear'
        | 'banned';
    severity: 'block' | 'warn';
    phrase: string | null;
    fix: string | null;
    reason: string | null;
    citation: string;
    url: string;
    source: 'rule' | 'claude';
};

export type ComplianceResult = { ok: boolean; issues: ComplianceIssue[] };
