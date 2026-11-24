<?php

declare(strict_types=1);

namespace App\StateRules;

/**
 * Texas, seeded from TREC (A5.2). Each rule names the paragraph it comes from
 * and when that text took effect; values were checked against the source on
 * 22 November 2026. Super admins change them on screen 62 as new versions.
 *
 * @phpstan-type Seed array{key: string, value: array<string, mixed>, citation: string, source_url: string|null, effective_from: string}
 */
final class TexasRules
{
    private const TAC_535_92 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-92';

    private const TAC_535_155 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-155';

    private const RENEWAL = 'https://trec.texas.gov/i-am-renewing-my-license-how-do-i-know-how-many-classes-i-need-take-renewal';

    private const SALES_AGENT = 'https://www.trec.texas.gov/become-licensed/sales-agent';

    private const BROKER = 'https://www.trec.texas.gov/become-licensed/individual-real-estate-broker';

    private const PEARSON_VUE = 'https://www.pearsonvue.com/us/en/tx/realestate.html';

    private const TAC_535_65 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-65';

    private const HANDBOOK = 'https://www.pearsonvue.com/content/dam/VUE/vue/en/documents/publications/094400.pdf';

    private const RENEW_AGENT = 'https://www.trec.texas.gov/renew-license/real-estate-sales-agent';

    private const REALM = 'https://aca-prod.accela.com/TREC/Default.aspx';

    private const TAC_535_146 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-146';

    private const TAC_535_3 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-3';

    private const TAC_535_2 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-2';

    private const TAC_531_20 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-531-20';

    private const TAC_537_11 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-537-11';

    public const CHECKED_ON = '2026-11-22';

    /** @return list<Seed> */
    public static function all(): array
    {
        $ce = static fn (string $key, array $value, string $paragraph, string $url = self::TAC_535_92): array => [
            'key' => $key, 'value' => $value, 'citation' => $paragraph, 'source_url' => $url, 'effective_from' => '2023-08-29',
        ];

        return [
            ['key' => 'licensing.qualifying_hours', 'value' => ['number' => 180], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'licensing.qualifying_courses', 'value' => ['courses' => [
                ['code' => 'principles_1', 'title' => 'Principles of Real Estate I', 'hours' => 30],
                ['code' => 'principles_2', 'title' => 'Principles of Real Estate II', 'hours' => 30],
                ['code' => 'law_of_agency', 'title' => 'Law of Agency', 'hours' => 30],
                ['code' => 'law_of_contracts', 'title' => 'Law of Contracts', 'hours' => 30],
                ['code' => 'promulgated_contract_forms', 'title' => 'Promulgated Contract Forms', 'hours' => 30],
                ['code' => 'real_estate_finance', 'title' => 'Real Estate Finance', 'hours' => 30],
            ]], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'licensing.minimum_age', 'value' => ['number' => 18], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'exam.vendor', 'value' => ['text' => 'Pearson VUE'], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'licensing.steps', 'value' => ['steps' => self::steps()], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'licensing.application_months', 'value' => ['number' => 12], 'citation' => 'TREC: "Your application expires one year from the date it is filed."', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
            ['key' => 'exam.attempts', 'value' => ['number' => 3], 'citation' => 'Pearson VUE Texas candidate handbook: three attempts to pass both portions before the application expires; then 30 more hours of qualifying education (60 if both portions failed)', 'source_url' => self::HANDBOOK, 'effective_from' => '2023-08-29'],
            ['key' => 'exam.day_rules', 'value' => ['text' => 'Bring two current IDs with your signature; the first must be government-issued with your photo, and the names must match your registration exactly. Arrive 30 minutes early. No phones, watches, wallets or bags in the testing room. A hand-held financial calculator without letters is allowed; the centre doesn\'t provide one.'], 'citation' => 'Pearson VUE Texas candidate handbook: identification, arrival and personal items', 'source_url' => self::HANDBOOK, 'effective_from' => '2023-08-29'],
            ...self::exam(),
            // Agent modes (A11.1a): REOS's defaults from the Agent Modes Journey; Own follows TREC's broker experience rule.
            ['key' => 'agents.grow_closings', 'value' => ['number' => 1], 'citation' => 'REOS default (Agent Modes Journey): suggest Grow after the first verified closing', 'source_url' => null, 'effective_from' => '2026-11-24'],
            ['key' => 'agents.lead_closings', 'value' => ['number' => 12], 'citation' => 'REOS default (Agent Modes Journey): suggest Lead at about 12 closings in 12 months', 'source_url' => null, 'effective_from' => '2026-11-24'],
            ['key' => 'agents.own_years', 'value' => ['number' => 4], 'citation' => "TREC: a broker applicant needs four years' active experience as a sales agent or broker in the 60 months before applying", 'source_url' => self::BROKER, 'effective_from' => '2026-11-24'],
            $ce('ce.period_months', ['number' => 24], '22 TAC §535.92(a); TREC renewal requirements', self::RENEWAL),
            $ce('ce.hours', ['number' => 18], '22 TAC §535.92(a)'),
            $ce('ce.mandatory_courses', ['courses' => [
                ['code' => 'legal_update_1', 'title' => 'Legal Update I: Laws, Rules and Forms', 'hours' => 4],
                ['code' => 'legal_update_2', 'title' => 'Legal Update II: Agency, Ethics and Hot Topics', 'hours' => 4],
                ['code' => 'contracts', 'title' => 'Real estate contracts', 'hours' => 3],
            ]], '22 TAC §535.92(a)(1)–(3)'),
            $ce('ce.broker_responsibility', [
                'code' => 'broker_responsibility', 'title' => 'Broker Responsibility', 'hours' => 6,
                'roles' => ['sponsoring_broker', 'designated_broker', 'delegated_supervisor'],
            ], '22 TAC §535.92(a)(4)'),
            $ce('ce.first_renewal', ['qualifying_hours' => 90, 'courses' => ['legal_update_1', 'legal_update_2']], 'TREC renewal requirements: 270 qualifying hours in all, plus Legal Update I and II', self::RENEWAL),
            $ce('ce.no_repeat', ['text' => 'No credit for taking the same course more than once in the license period.'], '22 TAC §535.92(h)'),
            $ce('ce.commission_meeting', ['number' => 4], '22 TAC §535.92(f)'),
            $ce('ce.deferral', ['fee_usd' => 200, 'days' => 60], 'TREC renewal requirements: CE deferral fee', self::RENEWAL),
            // Renew your licence (A11.2, agent screen 106): the checklist and its links.
            ['key' => 'ce.renewal_steps', 'value' => ['steps' => self::renewalSteps()], 'citation' => 'TREC: Renew your real estate sales agent license', 'source_url' => self::RENEW_AGENT, 'effective_from' => '2026-11-24'],
            ['key' => 'advertising.license_holder_name', 'value' => ['text' => 'Every advertisement shows the name of the license holder or team placing it, in a readily noticeable location.'], 'citation' => '22 TAC §535.155(a)(1)', 'source_url' => self::TAC_535_155, 'effective_from' => '2018-09-04'],
            ['key' => 'advertising.broker_name_size', 'value' => ['text' => "The broker's name is at least half the size of the largest contact information for any sales agent, associated broker or team name in the advertisement."], 'citation' => '22 TAC §535.155(a)(2)', 'source_url' => self::TAC_535_155, 'effective_from' => '2018-09-04'],
            ['key' => 'advertising.social_media', 'value' => ['text' => 'Social media, websites, email and texts are advertisements. The required information can be on the profile or a page linked directly from the post, if readily noticeable there.'], 'citation' => '22 TAC §535.155(b)(1), (c)', 'source_url' => self::TAC_535_155, 'effective_from' => '2018-09-04'],
            ['key' => 'advertising.misleading_titles', 'value' => ['text' => 'A sales agent may not be presented as a broker, owner, president or similar, and a team name may not suggest a separate brokerage ("brokerage", "company", "associates").'], 'citation' => '22 TAC §535.155(d)(3)–(5)', 'source_url' => self::TAC_535_155, 'effective_from' => '2018-09-04'],
            // Classroom delivery: present for the hours credited, no daily segment over 12 hours, a makeup only after two-thirds attended, within 90 days.
            ['key' => 'courses.seat_time', 'value' => ['max_hours_per_day' => 12, 'presence_percent' => 100, 'makeup_min_percent' => 67, 'makeup_days' => 90], 'citation' => '22 TAC §535.65(g)(1)(B)(ii), (iv) and (g)(1)(D)', 'source_url' => self::TAC_535_65, 'effective_from' => '2023-06-11'],
            // The virtual broker's Texas library (A12.1, ADR 0047): effective_from is the day REOS checked the text against the source; the rule's own history is on the source page.
            ['key' => 'brokerage.trust_money', 'value' => ['text' => "Trust money a license holder receives (earnest money, option fees and similar deposits) goes into the broker's trust account, or to the escrow agent named in the contract, no later than the close of business on the second working day after all parties have signed the contract. It is never mixed with the broker's own money."], 'citation' => '22 TAC §535.146', 'source_url' => self::TAC_535_146, 'effective_from' => '2026-11-24'],
            ['key' => 'brokerage.compensation', 'value' => ['text' => 'A sales agent may accept compensation for real estate brokerage services only from their sponsoring broker, and pays any commission or referral fee only through that broker.'], 'citation' => '22 TAC §535.3', 'source_url' => self::TAC_535_3, 'effective_from' => '2026-11-24'],
            ['key' => 'brokerage.supervision', 'value' => ['text' => "A broker is responsible for the authorized acts of the sales agents they sponsor, and keeps written policies and procedures so those agents' work is supervised. Agents follow the brokerage's policies."], 'citation' => '22 TAC §535.2', 'source_url' => self::TAC_535_2, 'effective_from' => '2026-11-24'],
            ['key' => 'brokerage.iabs', 'value' => ['text' => 'At the first substantive communication with a party about a specific property, a license holder gives them the Information About Brokerage Services notice (TREC form IABS 1-0).'], 'citation' => '22 TAC §531.20', 'source_url' => self::TAC_531_20, 'effective_from' => '2026-11-24'],
            ['key' => 'forms.promulgated', 'value' => ['text' => "License holders use TREC's promulgated contract forms when a form exists for the transaction. They may fill in factual statements and business details, but may not draft legal clauses or give legal advice: that is the unauthorized practice of law."], 'citation' => '22 TAC §537.11', 'source_url' => self::TAC_537_11, 'effective_from' => '2026-11-24'],
            ['key' => 'brokerage.sponsorship', 'value' => ['text' => 'A sales agent must be sponsored by a licensed broker to perform any act of real estate services.'], 'citation' => 'TREC: Become a Real Estate Sales Agent', 'source_url' => self::SALES_AGENT, 'effective_from' => '2023-08-29'],
        ];
    }

    /**
     * The steps to an active Texas sales agent licence (TREC), in order.
     *
     * @return list<array{code: string, title: string, detail: string, url: string}>
     */
    public static function steps(): array
    {
        return [
            ['code' => 'education', 'title' => 'Qualifying education', 'detail' => 'Finish the six 30-hour qualifying courses (180 hours) with an approved provider. Create your REALM account first and give the provider your pre-licence education number.', 'url' => self::SALES_AGENT],
            ['code' => 'application', 'title' => 'Application', 'detail' => 'Apply online in REALM (Sales Agent Application) and upload your course completions. The application expires one year from the date you file it.', 'url' => 'https://www.trec.texas.gov/article/realm-portal-real-estate-applicants'],
            ['code' => 'fingerprints', 'title' => 'Fingerprints', 'detail' => 'When your application is In Review, TREC emails how to schedule fingerprints with IdentoGO. You need to pass the background check before a licence is issued.', 'url' => self::SALES_AGENT],
            ['code' => 'exam', 'title' => 'Exam', 'detail' => 'After TREC reviews your education, it emails how to schedule the national and state portions with Pearson VUE. Both must be passed before the application expires.', 'url' => self::PEARSON_VUE],
            ['code' => 'sponsor', 'title' => 'Sponsoring broker', 'detail' => 'Your licence is issued inactive. A licensed broker sponsors you in REALM so you can practice.', 'url' => self::SALES_AGENT],
            ['code' => 'active', 'title' => 'Active licence', 'detail' => 'Once a broker sponsors you, your licence is active. Enter your licence number in REOS to switch to agent.', 'url' => 'https://www.trec.texas.gov/apps/license-holder-search/'],
        ];
    }

    /**
     * Renewing an active licence (A11.2): CE reported, a sponsoring broker,
     * renew in REALM, then REOS confirms the new expiry on TREC's record.
     *
     * @return list<array{code: string, title: string, detail: string, url: string|null}>
     */
    public static function renewalSteps(): array
    {
        return [
            ['code' => 'ce_reported', 'title' => 'CE reported', 'detail' => 'Finish your CE at least 10 days before your license expires, so the providers can report it to TREC in time. TREC also accepts a CE deferral fee.', 'url' => self::RENEWAL],
            ['code' => 'sponsor', 'title' => 'Sponsoring broker', 'detail' => 'An active sales agent license needs a sponsoring broker on your TREC record.', 'url' => 'https://www.trec.texas.gov/apps/license-holder-search/'],
            ['code' => 'renew', 'title' => 'Renew with TREC', 'detail' => 'TREC sends a renewal notice about 90 days before your license expires; renew in REALM once you have it. The renewal fee is paid to TREC, not REOS.', 'url' => self::REALM],
            ['code' => 'confirm', 'title' => 'Confirmed', 'detail' => "REOS checks TREC's record each night and marks this done when your new expiry date appears.", 'url' => null],
        ];
    }

    /**
     * The sales agent exam (Pearson VUE Texas candidate handbook): National
     * 85 items (80 scored) in 150 minutes, pass at 56; Texas 50 items (40
     * scored) in 90 minutes, pass at 28.
     *
     * @return list<Seed>
     */
    private static function exam(): array
    {
        $values = [
            'exam.national.questions' => 85, 'exam.national.scored' => 80, 'exam.national.minutes' => 150, 'exam.national.pass' => 56,
            'exam.state.questions' => 50, 'exam.state.scored' => 40, 'exam.state.minutes' => 90, 'exam.state.pass' => 28,
        ];
        $seeds = [];

        foreach ($values as $key => $number) {
            $seeds[] = ['key' => $key, 'value' => ['number' => $number], 'citation' => 'Pearson VUE: Texas Real Estate candidate handbook (sales agent exam)', 'source_url' => self::PEARSON_VUE, 'effective_from' => '2023-08-29'];
        }

        return $seeds;
    }
}
