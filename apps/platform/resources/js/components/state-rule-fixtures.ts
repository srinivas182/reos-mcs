import type { RuleRow, RuleVersion } from '@/lib/state-rules';

const v = (
    id: number,
    value: RuleVersion['value'],
    citation: string,
    extra: Partial<RuleVersion> = {},
): RuleVersion => ({
    id,
    version: 1,
    status: 'published',
    value,
    citation,
    source_url:
        'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-92',
    effective_from: '2023-08-29',
    checked_on: '2026-11-22',
    note: null,
    published_at: '2026-11-22T10:00:00+00:00',
    published_by: null,
    ...extra,
});

const row = (
    key: string,
    type: RuleRow['type'],
    unit: string | null,
    current: RuleVersion,
    used_by: string[],
    extra: Partial<RuleRow> = {},
): RuleRow => ({
    key,
    topic: key.split('.')[0] as RuleRow['topic'],
    type,
    unit,
    used_by,
    current,
    draft: null,
    scheduled: null,
    versions: [current],
    ...extra,
});

/** Texas CE rules as seeded from TREC (screen 62). */
export const texasRules: RuleRow[] = [
    row(
        'ce.period_months',
        'number',
        'months',
        v(1, { number: 24 }, '22 TAC §535.92(a)'),
        ['ce_card', 'broker_compliance', 'reminders'],
    ),
    row(
        'ce.hours',
        'number',
        'hours',
        v(2, { number: 18 }, '22 TAC §535.92(a)'),
        ['ce_card', 'broker_compliance', 'course_catalogue'],
        {
            draft: v(20, { number: 20 }, '22 TAC §535.92(a)', {
                version: 2,
                status: 'draft',
                effective_from: '2027-09-01',
                published_at: null,
            }),
        },
    ),
    row(
        'ce.mandatory_courses',
        'courses',
        null,
        v(
            3,
            {
                courses: [
                    {
                        code: 'legal_update_1',
                        title: 'Legal Update I: Laws, Rules and Forms',
                        hours: 4,
                    },
                    {
                        code: 'legal_update_2',
                        title: 'Legal Update II: Agency, Ethics and Hot Topics',
                        hours: 4,
                    },
                    {
                        code: 'contracts',
                        title: 'Real estate contracts',
                        hours: 3,
                    },
                ],
            },
            '22 TAC §535.92(a)(1)–(3)',
        ),
        ['ce_card', 'broker_compliance', 'course_catalogue'],
    ),
    row(
        'ce.broker_responsibility',
        'role_course',
        null,
        v(
            4,
            {
                code: 'broker_responsibility',
                title: 'Broker Responsibility',
                hours: 6,
                roles: [
                    'sponsoring_broker',
                    'designated_broker',
                    'delegated_supervisor',
                ],
            },
            '22 TAC §535.92(a)(4)',
        ),
        ['ce_card', 'broker_compliance', 'course_catalogue'],
    ),
    row(
        'ce.first_renewal',
        'first_renewal',
        null,
        v(
            5,
            {
                qualifying_hours: 90,
                courses: ['legal_update_1', 'legal_update_2'],
            },
            'TREC renewal requirements',
            { source_url: null },
        ),
        ['ce_card', 'broker_compliance'],
    ),
    row(
        'ce.deferral',
        'fee_days',
        null,
        v(
            6,
            { fee_usd: 200, days: 60 },
            'TREC renewal requirements: CE deferral fee',
            { source_url: null },
        ),
        ['ce_card', 'reminders'],
    ),
    row(
        'exam.vendor',
        'text',
        null,
        v(7, { text: 'Pearson VUE' }, 'TREC: Become a Real Estate Sales Agent'),
        ['licence_journey', 'test_prep'],
    ),
];
