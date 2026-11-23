import type { TFunction } from 'i18next';

/** One version of a state rule (screen 62, A5.2). */
export type RuleVersion = {
    id: number;
    version: number;
    status: 'draft' | 'published' | 'retired';
    value: RuleValue;
    citation: string;
    source_url: string | null;
    effective_from: string;
    checked_on: string | null;
    note: string | null;
    published_at: string | null;
    published_by: string | null;
};

export type RuleTopic =
    | 'licensing'
    | 'exam'
    | 'ce'
    | 'advertising'
    | 'brokerage'
    | 'forms'
    | 'courses';

export type RuleType =
    | 'number'
    | 'courses'
    | 'role_course'
    | 'first_renewal'
    | 'fee_days'
    | 'text'
    | 'seat_time'
    | 'steps';

export type Course = { code: string; title: string; hours: number };

/** A step of the licence journey (A10.3a). */
export type Step = {
    code: string;
    title: string;
    detail: string;
    url: string | null;
};

export type RuleValue = {
    number?: number;
    courses?: Course[] | string[];
    code?: string;
    title?: string;
    hours?: number;
    roles?: string[];
    qualifying_hours?: number;
    fee_usd?: number;
    days?: number;
    text?: string;
    max_hours_per_day?: number;
    presence_percent?: number;
    makeup_min_percent?: number;
    makeup_days?: number;
    steps?: Step[];
};

export type RuleRow = {
    key: string;
    topic: RuleTopic;
    type: RuleType;
    unit: string | null;
    used_by: string[];
    current: RuleVersion | null;
    draft: RuleVersion | null;
    scheduled: RuleVersion | null;
    versions: RuleVersion[];
};

/** "18 hours", "2 years", "Legal Update I (4), …", "$200 for 60 more days". */
export function ruleText(
    t: TFunction,
    type: RuleType,
    unit: string | null,
    value: RuleValue,
): string {
    switch (type) {
        case 'number': {
            const n = value.number ?? 0;

            if (unit === 'months' && n % 12 === 0) {
                return t('stateRules.units.years', { count: n / 12 });
            }

            return unit
                ? t(
                      `stateRules.units.${unit as 'hours' | 'months' | 'years' | 'questions' | 'minutes' | 'correct' | 'attempts'}`,
                      {
                          count: n,
                      },
                  )
                : String(n);
        }
        case 'courses':
            return ((value.courses ?? []) as Course[])
                .map((c) => `${c.title} (${c.hours})`)
                .join(', ');
        case 'role_course':
            return t('stateRules.values.roleCourse', {
                hours: value.hours ?? 0,
                roles: (value.roles ?? [])
                    .map((r) =>
                        t(
                            `licences.roles.${r as 'sponsoring_broker' | 'designated_broker' | 'delegated_supervisor'}`,
                        ),
                    )
                    .join('; '),
            });
        case 'first_renewal':
            return t('stateRules.values.firstRenewal', {
                hours: value.qualifying_hours ?? 0,
                count: (value.courses ?? []).length,
            });
        case 'fee_days':
            return t('stateRules.values.feeDays', {
                fee: value.fee_usd ?? 0,
                days: value.days ?? 0,
            });
        case 'text':
            return value.text ?? '';
        case 'steps':
            return t('stateRules.values.steps', {
                count: (value.steps ?? []).length,
                titles: (value.steps ?? []).map((s) => s.title).join(', '),
            });
        case 'seat_time':
            return t('stateRules.values.seatTime', {
                max: value.max_hours_per_day ?? 0,
                presence: value.presence_percent ?? 0,
                makeup: value.makeup_min_percent ?? 0,
                days: value.makeup_days ?? 0,
            });
    }
}

/** A date such as "Aug 29, 2023". */
export function ruleDate(day: string | null, language: string): string {
    return day
        ? new Intl.DateTimeFormat(language, {
              dateStyle: 'medium',
              timeZone: 'UTC',
          }).format(new Date(`${day}T00:00:00Z`))
        : '';
}
