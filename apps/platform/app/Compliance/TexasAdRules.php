<?php

declare(strict_types=1);

namespace App\Compliance;

/**
 * Texas advertising rules, always on (B13.1, ADR 0057), plus fair housing
 * and the brokerage's own phrases. Rules are data: each has its citation,
 * the channels it covers and its fix. Checked against TREC's rules on the
 * date below.
 */
final class TexasAdRules
{
    public const CHECKED_ON = '2026-11-27';

    public const IABS = 'https://www.trec.texas.gov/forms/information-about-brokerage-services';

    public const CPN = 'https://www.trec.texas.gov/forms/consumer-protection-notice';

    private const TAC_535_154 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-154';

    private const TAC_535_155 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-535-155';

    private const TAC_531_18 = 'https://www.law.cornell.edu/regulations/texas/22-Tex-Admin-Code-SS-531-18';

    private const FHA = 'https://www.law.cornell.edu/uscode/text/42/3604';

    private const OCC_1101_351 = 'https://statutes.capitol.texas.gov/Docs/OC/htm/OC.1101.htm#1101.351';

    /** What a candidate can't say before their license is active: offering services, listings, prices, solicitation. */
    public const UNLICENSED = [
        'just listed', 'for sale', 'open house', 'my listing', 'my listings', 'list your home', 'list with me', 'sell your home', 'buy or sell', 'buying or selling',
        'call me', 'text me', 'dm me', 'contact me', 'help you buy', 'help you sell', 'home valuation', 'price reduced', 'join my brokerage', 'join our brokerage',
        'join my team', 'now hiring', 'recruiting agents', 'en venta', 'casa abierta', 'llámeme', 'comprar o vender', 'compre o venda', 'únase a mi agencia',
    ];

    /** Ads: posts and designs, videos, the public page and market update emails (B14.3c). Messages: emails and texts to contacts. */
    private const ADS = ['post', 'video', 'page', 'newsletter'];

    private const ALL = ['post', 'video', 'page', 'email', 'text', 'newsletter'];

    /** @return list<Rule> */
    public static function all(): array
    {
        return [
            // The broker's name in every advertisement (22 TAC §535.155(a)–(c)).
            new Rule('broker', '22 TAC §535.155(c)', self::TAC_535_155, self::ADS, 'block', 'compliance.fix.broker'),
            // At least half the size of the largest contact information (§535.155(a)(2)).
            new Rule('broker_size', '22 TAC §535.155(a)(2)', self::TAC_535_155, ['post', 'video'], 'block', 'compliance.fix.brokerSize'),
            // A team name only with the broker's name, and never with "realty", "brokerage" or similar (§535.154).
            new Rule('team_name', '22 TAC §535.154', self::TAC_535_154, self::ALL, 'block', 'compliance.fix.teamName'),
            // The IABS and Consumer Protection Notice links on the agent's website and social profiles (§531.18, §531.20).
            new Rule('links', '22 TAC §531.18, §531.20', self::TAC_531_18, ['page'], 'block', 'compliance.fix.links'),
            // No misleading advertising, including a sales agent presented as a broker (§535.155(d)).
            new Rule('claims', '22 TAC §535.155(d)', self::TAC_535_155, self::ALL, 'block', 'compliance.fix.claims'),
            new Rule('title', '22 TAC §535.155(d)', self::TAC_535_155, self::ALL, 'block', 'compliance.fix.title'),
            // Fair housing: describe the property, not who should live there (42 U.S.C. §3604(c)).
            new Rule('fair_housing', '42 U.S.C. §3604(c)', self::FHA, self::ALL, 'block', 'compliance.fix.fairHousing'),
            // Wording that depends on how it's used; a second opinion decides.
            new Rule('unclear', '42 U.S.C. §3604(c)', self::FHA, self::ALL, 'warn', 'compliance.fix.unclear'),
            // Before the license is active, no service advertising: no listings, prices, "call me to buy or sell" or
            // brokerage solicitation (Tex. Occ. Code §1101.351). "Future agent" wording is fine (B13.3).
            new Rule('unlicensed', 'Tex. Occ. Code §1101.351', self::OCC_1101_351, self::ADS, 'block', 'compliance.fix.unlicensed'),
            // The brokerage's own banned phrases (B12.1; every channel from B13.2).
            new Rule('banned', 'brokerage', '', self::ALL, 'block', 'compliance.fix.banned'),
        ];
    }

    public static function rule(string $code): Rule
    {
        foreach (self::all() as $rule) {
            if ($rule->code === $code) {
                return $rule;
            }
        }

        throw new \InvalidArgumentException("Unknown compliance rule {$code}.");
    }

    /** Words a team name may not use (§535.154(e)). */
    public const TEAM_WORDS = ['realty', 'realtors', 'brokerage', 'broker', 'brokers', 'real estate company', 'properties', 'homes inc', 'associates'];
}
