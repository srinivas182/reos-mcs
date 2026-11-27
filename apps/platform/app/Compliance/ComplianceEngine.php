<?php

declare(strict_types=1);

namespace App\Compliance;

use App\Authorization\Role;
use App\Enums\LicenceType;
use App\Enums\OrganisationType;
use App\Models\Licence;
use App\Models\User;
use App\Services\Ai\Bedrock;
use App\Services\Authorization\ActiveRole;
use App\Services\States\PersonStates;
use App\Studio\AdvertisingPolicy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The compliance engine (B13.1, ADR 0057): one set of rules for every
 * outbound channel — social posts and designs, studio videos, emails and
 * texts to contacts, and the agent's public page.
 *
 * Rules are data (`TexasAdRules`): the broker's name in every ad and at
 * least half the size of the largest contact information, team names,
 * the IABS and Consumer Protection Notice links, no misleading claims,
 * fair housing wording with context exceptions, and the brokerage's own
 * phrases. Unclear wording gets Claude's second opinion when Claude is
 * configured (free to the agent); otherwise the agent is asked to check it.
 *
 * @phpstan-type Content array{channel: string, text: string, attribution?: string|null, broker?: string|null, layout?: array{rendered: string, broker_size: int, largest_contact: int}|null, links?: list<string>|null}
 * @phpstan-type Issue array{code: string, severity: 'block'|'warn', phrase: string|null, fix: string|null, reason: string|null, citation: string, url: string, source: 'rule'|'claude'}
 * @phpstan-type Result array{ok: bool, issues: list<Issue>}
 */
final readonly class ComplianceEngine
{
    public const CHANNELS = ['post', 'video', 'page', 'email', 'text'];

    public function __construct(
        private AdvertisingPolicy $policy,
        private ActiveRole $roles,
        private PersonStates $states,
        private Bedrock $bedrock,
    ) {}

    /**
     * Checks one piece of outbound content.
     *
     * @param  Content  $content
     * @return Result
     */
    public function check(User $agent, array $content): array
    {
        $channel = $content['channel'];
        $text = $content['text'];
        $whole = trim($text."\n".($content['attribution'] ?? ''));
        $issues = [];

        // Wording: fair housing, misleading claims and the brokerage's phrases.
        $found = $this->policy->issues($agent, $text);

        foreach ($found as $f) {
            $issues[] = self::issue($f['code'], $f['phrase'], $f['fix'], null, 'rule');
        }

        // A candidate (B13.3) isn't licensed yet: no broker to name, and no service advertising at all.
        $candidate = $this->roles->for($agent)?->role === Role::Candidate;

        if ($candidate) {
            $lower = mb_strtolower($text);

            foreach (TexasAdRules::UNLICENSED as $phrase) {
                if (preg_match('/(?<![\p{L}])'.preg_quote($phrase, '/').'(?![\p{L}])/u', $lower) === 1) {
                    $issues[] = self::issue('unlicensed', $phrase, '', null, 'rule');
                }
            }

            if (preg_match('/\$\d[\d,]*(?:\.\d+)?(?:\s?[kKmM]\b)?/u', $text, $price) === 1) {
                $issues[] = self::issue('unlicensed', $price[0], '', null, 'rule');
            }
        }

        // The broker's name in every ad.
        // The broker the content must name: given (the public page shows the MLS office), else the agent's.
        $broker = array_key_exists('broker', $content) ? $content['broker'] : $this->brokerName($agent);
        $named = $broker !== null && str_contains(mb_strtolower($whole), mb_strtolower($broker));

        if (! $candidate && TexasAdRules::rule('broker')->appliesTo($channel) && ! $named) {
            $issues[] = self::issue('broker', $broker, null, null, 'rule');
        }

        // …and at least half the size of the largest contact information, in full, on designs and videos.
        $layout = $content['layout'] ?? null;

        if (! $candidate && $layout !== null && TexasAdRules::rule('broker_size')->appliesTo($channel)) {
            $whole_rendered = $broker !== null && str_contains(mb_strtolower($layout['rendered']), mb_strtolower($broker));

            if (! $whole_rendered || $layout['largest_contact'] > $layout['broker_size'] * 2) {
                $issues[] = self::issue('broker_size', $broker, null, null, 'rule');
            }
        }

        // Team names: with the broker's name, and without words that make them sound like a brokerage.
        foreach ($candidate ? [] : self::teams($whole) as $team) {
            $lower = mb_strtolower($team);

            if (array_any(TexasAdRules::TEAM_WORDS, static fn (string $w): bool => str_contains($lower, $w)) || ! $named) {
                $issues[] = self::issue('team_name', $team, null, null, 'rule');
            }
        }

        // The IABS and Consumer Protection Notice links on the public page.
        if (TexasAdRules::rule('links')->appliesTo($channel)) {
            $links = $content['links'] ?? [];

            foreach ([TexasAdRules::IABS, TexasAdRules::CPN] as $url) {
                if (! in_array($url, $links, true)) {
                    $issues[] = self::issue('links', $url, null, null, 'rule');
                }
            }
        }

        // A sales agent presented as a broker, owner or president.
        $licence = Licence::query()->where('user_id', $agent->id)->where('state_code', $this->states->current($agent))->first();

        if ($licence?->type === LicenceType::SalesAgent && preg_match("/\\b(i am|i'm|as your|your)\\s+(real estate\\s+)?(broker|owner|president)\\b/i", $text) === 1) {
            $issues[] = self::issue('title', null, null, null, 'rule');
        }

        // Unclear wording: a second opinion.
        foreach ($this->secondOpinion(Wording::unclear($text, array_column($found, 'phrase'))) as $issue) {
            $issues[] = $issue;
        }

        $issues = array_values(array_filter($issues, static fn (array $i): bool => TexasAdRules::rule($i['code'])->appliesTo($channel)));

        return ['ok' => ! array_any($issues, static fn (array $i): bool => $i['severity'] === 'block'), 'issues' => $issues];
    }

    /**
     * Fix automatically: flagged wording swapped or taken out, including what Claude judged a problem.
     *
     * @param  list<Issue>  $issues
     */
    public function fix(User $agent, string $text, array $issues = []): string
    {
        $text = $this->policy->fix($agent, $text);

        foreach ($issues as $issue) {
            if ($issue['source'] === 'claude' && $issue['phrase'] !== null) {
                $text = (string) preg_replace('/(?<![\p{L}])'.preg_quote($issue['phrase'], '/').'(?![\p{L}])/iu', $issue['fix'] ?? '', $text);
            }
        }

        return trim((string) preg_replace('/[ \t]{2,}/u', ' ', $text));
    }

    /** The agent's brokerage, or the sponsoring broker on their licence. */
    public function brokerName(User $agent): ?string
    {
        $membership = $this->roles->for($agent);

        if ($membership !== null && $membership->role === Role::Agent && $membership->organisation->type === OrganisationType::Brokerage) {
            return $membership->organisation->name;
        }

        $brokerage = $this->policy->brokerage($agent);

        if ($brokerage !== null) {
            return $brokerage->name;
        }

        $sponsor = Licence::query()->where('user_id', $agent->id)->where('state_code', $this->states->current($agent))->first()?->sponsor_name;

        return is_string($sponsor) && $sponsor !== '' ? $sponsor : null;
    }

    /**
     * Team names in the text ("The Miller Team", "Bayou Home Group").
     *
     * @return list<string>
     */
    public static function teams(string $text): array
    {
        preg_match_all("/\\b((?:\\p{Lu}[\\p{L}'’&-]*\\s+){1,4})(Team|Group|Equipo|Grupo)\\b/u", $text, $m);

        return array_values(array_unique(array_map(static fn (string $t): string => trim($t), $m[0])));
    }

    /**
     * @param  list<array{phrase: string, sentence: string}>  $unclear
     * @return list<Issue>
     */
    private function secondOpinion(array $unclear): array
    {
        if ($unclear === []) {
            return [];
        }

        $ask = static fn (): array => array_map(static fn (array $u): array => self::issue('unclear', $u['phrase'], null, null, 'rule'), $unclear);
        $bedrock = $this->bedrock->client(10);

        if ($bedrock === null) {
            return $ask();
        }

        try {
            $verdicts = Cache::remember('compliance-opinion:'.md5((string) json_encode($unclear)), now()->addDay(), fn (): array => (new ClaudeSecondOpinion($bedrock['client'], $bedrock['model']))->judge($unclear));
        } catch (Throwable $e) {
            Log::warning('compliance.second_opinion_failed', ['error' => $e->getMessage()]);

            return $ask();
        }

        $out = [];

        foreach ($unclear as $u) {
            $v = $verdicts[$u['phrase']] ?? null;

            if ($v === null) {
                $out[] = self::issue('unclear', $u['phrase'], null, null, 'rule');
            } elseif ($v['problem']) {
                $out[] = self::issue('fair_housing', $u['phrase'], $v['fix'], $v['reason'] !== '' ? $v['reason'] : null, 'claude');
            }
        }

        return $out;
    }

    /**
     * @param  'rule'|'claude'  $source
     * @return Issue
     */
    private static function issue(string $code, ?string $phrase, ?string $fix, ?string $reason, string $source): array
    {
        $rule = TexasAdRules::rule($code);

        return ['code' => $code, 'severity' => $rule->severity, 'phrase' => $phrase, 'fix' => $fix, 'reason' => $reason, 'citation' => $rule->citation, 'url' => $rule->url, 'source' => $source];
    }
}
