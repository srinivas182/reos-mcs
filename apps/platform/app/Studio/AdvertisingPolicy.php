<?php

declare(strict_types=1);

namespace App\Studio;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\Licence;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;

/**
 * Advertising checks (B12.1, ADR 0050; agent screen 167, broker web
 * screen 35). Texas rules are locked on; the brokerage adds its own:
 * banned phrases, and broker approval for agents in their first 90 days
 * or for everyone. "Fix automatically" swaps flagged wording for the
 * suggested words; a phrase with nothing to swap is taken out.
 *
 * Fair housing wording follows HUD's advertising guidance: describe the
 * property, not who should live there. Misleading claims (guarantees,
 * "best price") are the TREC rule against misleading advertising.
 *
 * @phpstan-type Rules array{approval_new_agents: bool, approval_all: bool, banned: list<string>}
 * @phpstan-type Issue array{code: 'fair_housing'|'claims'|'banned', phrase: string, fix: string}
 */
final readonly class AdvertisingPolicy
{
    /** Wording about who should live there, with words about the home instead. */
    public const FAIR_HOUSING = [
        'perfect for families' => 'roomy layout', 'family-friendly' => 'welcoming', 'family friendly' => 'welcoming', 'ideal for families' => 'roomy layout',
        'great for kids' => 'big backyard', 'no children' => '', 'no kids' => '', 'adults only' => '', 'perfect for couples' => 'inviting home',
        'ideal for singles' => 'easy-care home', 'bachelor pad' => 'stylish home', 'empty nesters' => 'easy-care living', 'exclusive neighborhood' => 'established neighborhood',
        'safe neighborhood' => 'quiet street', 'walking distance to church' => 'close to local amenities', 'christian' => '',
        'perfecta para familias' => 'distribución amplia', 'ideal para familias' => 'distribución amplia', 'sin niños' => '', 'solo adultos' => '', 'vecindario exclusivo' => 'vecindario establecido',
    ];

    /** Claims a listing can't promise (TREC: no misleading advertising). */
    public const CLAIMS = [
        'guaranteed' => '', 'guarantee' => '', 'best price in town' => 'great value', 'lowest price' => 'great value', 'will sell fast' => 'just listed',
        'garantizado' => '', 'el mejor precio' => 'excelente valor',
    ];

    public const APPROVAL_DAYS = 90;

    public function __construct(private Memberships $memberships, private AuditLogger $audit) {}

    /** @return Rules */
    public function rules(Organisation $brokerage): array
    {
        $saved = $brokerage->advertising ?? [];

        return [
            'approval_new_agents' => ($saved['approval_new_agents'] ?? false) === true,
            'approval_all' => ($saved['approval_all'] ?? false) === true,
            'banned' => array_values(array_filter($saved['banned'] ?? [], static fn (string $p): bool => trim($p) !== '')),
        ];
    }

    /** @param  list<string>  $banned */
    public function save(Organisation $brokerage, bool $newAgents, bool $all, array $banned, User $actor): void
    {
        $before = $this->rules($brokerage);
        $clean = array_values(array_unique(array_filter(array_map(static fn (string $p): string => mb_substr(trim($p), 0, 80), $banned), static fn (string $p): bool => $p !== '')));
        $brokerage->forceFill(['advertising' => ['approval_new_agents' => $newAgents, 'approval_all' => $all, 'banned' => array_slice($clean, 0, 100)]])->save();
        $this->audit->record('advertising_policy.updated', 'organisation', (string) $brokerage->id, ['before' => $before, 'after' => $this->rules($brokerage)], $actor, $brokerage->id);
    }

    /**
     * What a post's words break, with the fix for each.
     *
     * @return list<Issue>
     */
    public function issues(User $agent, string $text): array
    {
        $issues = [];
        $lower = mb_strtolower($text);
        $brokerage = $this->brokerage($agent);
        $lists = [
            'fair_housing' => self::FAIR_HOUSING,
            'claims' => self::CLAIMS,
            'banned' => $brokerage === null ? [] : array_fill_keys(array_map('mb_strtolower', $this->rules($brokerage)['banned']), ''),
        ];

        foreach ($lists as $code => $phrases) {
            foreach ($phrases as $phrase => $fix) {
                if (preg_match('/(?<![\p{L}])'.preg_quote((string) $phrase, '/').'(?![\p{L}])/u', $lower) === 1) {
                    $issues[] = ['code' => $code, 'phrase' => (string) $phrase, 'fix' => $fix];
                }
            }
        }

        return $issues;
    }

    /** The post's words with every flagged phrase swapped or taken out. */
    public function fix(User $agent, string $text): string
    {
        foreach ($this->issues($agent, $text) as $issue) {
            $text = (string) preg_replace('/(?<![\p{L}])'.preg_quote($issue['phrase'], '/').'(?![\p{L}])/iu', $issue['fix'], $text);
        }

        // Tidy what removals leave behind.
        $text = (string) preg_replace(['/[ \t]{2,}/u', '/\s+([,.!?])/u', '/,\s*,/u', '/,([.!?])/u', '/^\s*[,.]\s*/mu'], [' ', '$1', ',', '$1', ''], $text);

        return trim($text);
    }

    /** Whether the brokerage wants to see this agent's posts before they go out. */
    public function needsApproval(User $agent): bool
    {
        $brokerage = $this->brokerage($agent);

        if ($brokerage === null) {
            return false;
        }

        $rules = $this->rules($brokerage);

        if ($rules['approval_all']) {
            return true;
        }

        $licence = Licence::query()->where('user_id', $agent->id)->orderByDesc('licensed_on')->first();
        $licensed = $licence?->licensed_on;

        return $rules['approval_new_agents'] && ($licensed === null || CarbonImmutable::instance($licensed)->gt(CarbonImmutable::now()->subDays(self::APPROVAL_DAYS)));
    }

    public function brokerage(User $agent): ?Organisation
    {
        foreach ($this->memberships->of($agent) as $membership) {
            if ($membership->role === Role::Agent && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation;
            }
        }

        return null;
    }
}
