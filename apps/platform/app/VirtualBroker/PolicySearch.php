<?php

declare(strict_types=1);

namespace App\VirtualBroker;

use App\Content\Knowledge\KnowledgeSearch;
use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\PolicyPassage;
use App\Models\StateRule;
use App\Services\StateRules\StateRules;
use App\StateRules\RuleCatalogue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Finds what an answer can come from (A12.1, ADR 0047): the brokerage's
 * current policy manual, its FAQ (the broker's answers) and the state's
 * rules the virtual broker cites. Ranked by how many of the question's
 * words a passage holds, so a manual of a few hundred pages stays fast
 * on SQLite and PostgreSQL alike and every result can be explained.
 *
 * @phpstan-type Hit array{kind: 'manual'|'faq'|'rule', id: string, title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string, score: float, position: int|null, trec_wins?: bool}
 */
final readonly class PolicySearch
{
    /** Words that say nothing about the topic (English and Spanish). */
    public const STOP = [
        'what', 'when', 'where', 'which', 'who', 'why', 'how', 'does', 'the', 'and', 'for', 'can', 'are', 'you', 'our', 'your', 'have', 'has', 'with', 'that',
        'this', 'there', 'about', 'into', 'from', 'should', 'would', 'could', 'need', 'must', 'may', 'will', 'any', 'all', 'get', 'its', 'it', 'is', 'do', 'to',
        'of', 'in', 'on', 'at', 'by', 'or', 'an', 'be', 'if', 'my', 'me', 'we', 'us', 'it', 'as', 'so', 'not', 'no', 'yes', 'okay', 'please', 'tell',
        'after', 'before', 'then', 'than', 'also', 'every', 'each', 'other', 'some', 'more', 'most', 'very', 'just', 'only', 'over', 'under', 'again', 'still', 'even',
        'much', 'many', 'same', 'such', 'own', 'both', 'few', 'here', 'them', 'they', 'their', 'these', 'those', 'his', 'her', 'him', 'she', 'was', 'were', 'been', 'being',
        'did', 'done', 'make', 'made', 'take', 'put', 'give', 'going', 'come', 'want', 'like', 'know', 'think', 'say', 'said', 'use', 'used', 'way', 'thing', 'things', 'bring',
        'que', 'qué', 'cuándo', 'cuando', 'cómo', 'como', 'por', 'para', 'una', 'los', 'las', 'del', 'con', 'mi', 'el', 'la', 'de', 'en', 'es', 'se', 'lo', 'un', 'puedo', 'debo',
    ];

    /** A passage holding less of the question than this isn't about it. */
    public const MIN_SCORE = 0.3;

    /** Rule keys the virtual broker reads. */
    public const FEATURE = 'virtual_broker';

    public function __construct(private StateRules $rules) {}

    /**
     * The best passages for a question, best first.
     *
     * @return list<Hit>
     */
    public function search(Organisation $brokerage, string $state, string $question, int $limit = 4): array
    {
        $words = self::terms($question);

        if ($words === []) {
            return [];
        }

        $hits = [];

        $passages = PolicyPassage::query()
            ->where('organisation_id', $brokerage->id)
            ->where(static function (Builder $q) use ($brokerage): void {
                $q->where('kind', PolicyPassage::FAQ)->orWhereIn('policy_manual_id', PolicyManual::query()->select('id')
                    ->where('organisation_id', $brokerage->id)->where('status', PolicyManual::CURRENT));
            })
            ->where(static function (Builder $q) use ($words): void {
                foreach ($words as $word) {
                    $q->orWhere('content', 'like', '%'.addcslashes($word, '%_\\').'%')->orWhere('heading', 'like', '%'.addcslashes($word, '%_\\').'%');
                }
            })
            ->limit(400)->get();

        foreach ($passages as $passage) {
            $score = self::score($words, $passage->content, $passage->heading);

            if ($score >= self::MIN_SCORE) {
                $faq = $passage->kind === PolicyPassage::FAQ;
                $hits[] = [
                    'kind' => $faq ? 'faq' : 'manual', 'id' => ($faq ? 'faq-' : 'manual-').$passage->id,
                    'title' => $passage->heading ?? '', 'citation' => null, 'page' => $passage->page, 'url' => null,
                    'effective_from' => $passage->created_at?->toDateString(), 'excerpt' => self::excerpt($passage->content),
                    // The broker's own answers rank a little higher than the manual for the same words.
                    'score' => min(1.0, $score + ($faq ? 0.1 : 0.0)), 'position' => $faq ? null : $passage->position,
                ];
            }
        }

        foreach ($this->stateRules($state) as $rule) {
            $text = self::ruleText($rule);
            $score = self::score($words, $text, str_replace(['.', '_'], ' ', $rule->key));

            if ($score >= self::MIN_SCORE) {
                $hits[] = self::ruleHit($rule, $score);
            }
        }

        usort($hits, static fn (array $a, array $b): int => [$b['score'], $a['kind'] === 'rule' ? 1 : 0] <=> [$a['score'], $b['kind'] === 'rule' ? 1 : 0]);

        return array_slice($hits, 0, $limit);
    }

    /**
     * The rules in force the virtual broker cites, by key.
     *
     * @return array<string, StateRule>
     */
    public function stateRules(string $state): array
    {
        return array_filter(
            $this->rules->inForce($state, CarbonImmutable::now()),
            static fn (StateRule $rule): bool => in_array(self::FEATURE, RuleCatalogue::find($rule->key)['used_by'] ?? [], true) && is_string($rule->value['text'] ?? null),
        );
    }

    /** @return Hit */
    public static function ruleHit(StateRule $rule, float $score = 1.0): array
    {
        return [
            'kind' => 'rule', 'id' => 'rule-'.$rule->key, 'title' => $rule->citation, 'citation' => $rule->citation, 'page' => null, 'url' => $rule->source_url,
            'effective_from' => $rule->effective_from->toDateString(), 'excerpt' => self::ruleText($rule), 'score' => $score, 'position' => null,
        ];
    }

    public static function ruleText(StateRule $rule): string
    {
        $text = $rule->value['text'] ?? '';

        return is_string($text) ? $text : '';
    }

    /**
     * The question's words that carry meaning, lightly stemmed.
     *
     * @return list<string>
     */
    public static function terms(string $text): array
    {
        $words = [];

        foreach (KnowledgeSearch::words($text) as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOP, true)) {
                continue;
            }
            $words[] = self::stem($word);
        }

        return array_values(array_unique($words));
    }

    /**
     * The share of the question's words a passage holds (0 to 1). One
     * shared word isn't enough when the question has more than one.
     *
     * @param  list<string>  $words
     */
    public static function score(array $words, string $content, ?string $heading = null): float
    {
        if ($words === []) {
            return 0.0;
        }

        $own = array_flip(self::terms($content.' '.($heading ?? '')));
        $found = 0;

        foreach ($words as $word) {
            if (isset($own[$word])) {
                $found++;
            }
        }

        return $found < min(2, count($words)) ? 0.0 : round($found / count($words), 3);
    }

    private static function stem(string $word): string
    {
        // "showings" → "showing" → "show"; "deposits" → "deposit".
        foreach (['s', 'ing'] as $end) {
            if (mb_strlen($word) > mb_strlen($end) + 3 && str_ends_with($word, $end) && ! str_ends_with($word, 'ss')) {
                $word = mb_substr($word, 0, -mb_strlen($end));
            }
        }

        return $word;
    }

    public static function excerpt(string $content, int $max = 600): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $content));

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)).'…';
    }
}
