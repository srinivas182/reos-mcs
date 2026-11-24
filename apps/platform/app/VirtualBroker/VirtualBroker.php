<?php

declare(strict_types=1);

namespace App\VirtualBroker;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\BrokerQuestion;
use App\Models\Deal;
use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\PolicyPassage;
use App\Models\User;
use App\Notifications\PolicyQuestionAnswered;
use App\Notifications\PolicyQuestionEscalated;
use App\Services\Ai\AiCredits;
use App\Services\Ai\Bedrock;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\Memberships;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * The virtual broker (A12.1, ADR 0047; agent screens 61 and 62, web
 * screens 16 and 34, broker journey screens 11 and 12).
 *
 * An agent asks; the answer comes from the brokerage's policy manual, its
 * FAQ and the state's rules, with a confidence level and citations. It is
 * free (the brokerage's own knowledge). It goes to the broker:
 *
 * - automatically when the sources don't answer clearly (low confidence),
 *   or when money or legal risk is involved, whatever the confidence;
 * - when the agent taps Send to my broker.
 *
 * The agent then sees "With your broker" and the time the brokerage aims
 * to reply by. The broker sees the question, why it came, and the virtual
 * broker's answer as a draft; sends it as is (Confirmed) or changed
 * (Corrected), labelled "From your broker", and can add it to the
 * brokerage FAQ so the next agent gets it straight away.
 *
 * @phpstan-import-type Hit from PolicySearch
 *
 * @phpstan-type Source array{kind: string, title: string, citation: string|null, page: int|null, url: string|null, effective_from: string|null, excerpt: string}
 * @phpstan-type QuestionRow array{id: string, question: string, answer: string, confidence: string, sources: list<Source>, status: string, reason: string|null, escalated_at: string|null, due_at: string|null, broker_answer: string|null, broker: string|null, answered_at: string|null, added_to_knowledge: bool, agent: string, deal: array{id: int, uuid: string, property: string}|null, created_at: string|null}
 */
final readonly class VirtualBroker
{
    public const FEATURE = 'virtual_broker';

    /** How fast the broker aims to reply unless the brokerage sets it. */
    public const REPLY_HOURS = 4;

    /** Money changing hands: always the broker's call. */
    private const MONEY = '/\b(earnest money|escrow|trust (account|money)|commissions?|splits?|referral fees?|rebates?|refunds?|deposits?|wire|option fees?|compensation|bonus(es)?|pay(ment|ing)?|paid|fees?|dinero|comisi[oó]n|dep[oó]sito|reembolso|transferencia|pago)\b/u';

    /** Legal risk: always the broker's call. */
    private const LEGAL = '/\b(lawsuits?|sue[ds]?|suing|attorneys?|lawyers?|legal|liab(le|ility)|breach|fraud|discriminat\w*|complaints?|demand letter|subpoena|terminat\w*|lien|demanda|abogad[oa]s?|fraude|queja|discrimina\w*)\b/u';

    public function __construct(
        private PolicySearch $search,
        private PolicyKnowledge $knowledge,
        private Bedrock $bedrock,
        private AiCredits $credits,
        private Memberships $memberships,
        private AuditLogger $audit,
    ) {}

    /** The brokerage an agent belongs to (404 when they have none). */
    public function brokerageOfAgent(User $agent): Organisation
    {
        return $this->brokerageFor($agent, Role::Agent);
    }

    /** The brokerage a broker runs (404 when they run none). */
    public function brokerageOfBroker(User $broker): Organisation
    {
        return $this->brokerageFor($broker, Role::Broker);
    }

    /**
     * An agent's question, answered and, when it should be, sent to the broker.
     *
     * @throws ValidationException
     */
    public function ask(User $agent, string $question, ?int $dealId): BrokerQuestion
    {
        $brokerage = $this->brokerageOfAgent($agent);

        if (! $this->credits->withinHourlyLimit($agent)) {
            throw new HttpException(429, self::line('crm.insight.limit'));
        }

        $deal = $dealId === null ? null : Deal::query()->where('user_id', $agent->id)->find($dealId);

        if ($dealId !== null && $deal === null) {
            throw ValidationException::withMessages(['deal_id' => self::line('virtualBroker.errors.deal')]);
        }

        $state = ($brokerage->states ?? [])[0] ?? 'TX';
        $language = $agent->locale->value;
        $hits = $this->withTexasFirst($brokerage, $this->search->search($brokerage, $state, $question));
        [$answer, $confidence, $sources, $needsBroker, $engine] = $this->answer($question, $hits, $brokerage, $state, $language);
        $this->credits->logFree($agent, self::FEATURE, $engine);

        $words = mb_strtolower($question);
        $reason = match (true) {
            preg_match(self::LEGAL, $words) === 1 => 'legal',
            preg_match(self::MONEY, $words) === 1 => 'money',
            $confidence === 'low' || $needsBroker => 'unclear',
            default => null,
        };

        $row = BrokerQuestion::query()->create([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $brokerage->id, 'user_id' => $agent->id, 'deal_id' => $deal?->id,
            'question' => trim($question), 'language' => $language, 'answer' => $answer, 'confidence' => $confidence,
            'sources' => array_map(self::source(...), $sources), 'status' => BrokerQuestion::ANSWERED, 'engine' => $engine,
        ]);

        return $reason === null ? $row : $this->escalate($row, $reason);
    }

    /** To the broker: "With your broker" and the time they aim to reply by. */
    public function escalate(BrokerQuestion $question, string $reason = 'asked'): BrokerQuestion
    {
        if ($question->status !== BrokerQuestion::ANSWERED) {
            return $question;
        }

        $brokerage = $question->organisation;
        $settings = $this->settings($brokerage);
        $question->forceFill([
            'status' => BrokerQuestion::WAITING, 'reason' => $reason, 'escalated_at' => now(),
            'due_at' => CarbonImmutable::now()->addHours($settings['reply_hours']),
        ])->save();

        foreach ($this->answerers($brokerage) as $broker) {
            $broker->notify(new PolicyQuestionEscalated($question));
        }

        return $question;
    }

    /**
     * The broker replies: as the draft (Confirmed) or changed (Corrected), and
     * optionally into the brokerage FAQ.
     */
    public function reply(BrokerQuestion $question, User $broker, string $text, bool $addToKnowledge): BrokerQuestion
    {
        if (in_array($question->status, [BrokerQuestion::CONFIRMED, BrokerQuestion::CORRECTED], true)) {
            throw ValidationException::withMessages(['answer' => self::line('virtualBroker.errors.answered')]);
        }

        $text = trim($text);
        $same = self::normalise($text) === self::normalise($question->answer);

        DB::transaction(function () use ($question, $broker, $text, $same, $addToKnowledge): void {
            $question->forceFill([
                'status' => $same ? BrokerQuestion::CONFIRMED : BrokerQuestion::CORRECTED, 'broker_answer' => $text, 'answered_by' => $broker->id,
                'answered_at' => now(), 'added_to_knowledge' => $addToKnowledge, 'escalated_at' => $question->escalated_at ?? now(),
            ])->save();

            if ($addToKnowledge) {
                $this->knowledge->addFaq($question->organisation, $question->question, $text, $question->id);
            }
        });

        $this->audit->record('broker_question.answered', 'broker_question', $question->uuid, ['outcome' => $question->status, 'added_to_knowledge' => $addToKnowledge], $broker, $question->organisation_id);
        $question->user->notify(new PolicyQuestionAnswered($question));

        return $question;
    }

    /** @return array{answerer_id: int|null, reply_hours: int} */
    public function settings(Organisation $brokerage): array
    {
        $saved = $brokerage->virtual_broker ?? [];
        $hours = $saved['reply_hours'] ?? self::REPLY_HOURS;

        return ['answerer_id' => $saved['answerer_id'] ?? null, 'reply_hours' => $hours > 0 ? $hours : self::REPLY_HOURS];
    }

    /** Who answers (a broker of the brokerage, or every broker) and how fast. */
    public function saveSettings(Organisation $brokerage, ?int $answererId, int $replyHours, User $actor): void
    {
        if ($answererId !== null && ! $this->memberships->holdersIn(Role::Broker, $brokerage)->contains('id', $answererId)) {
            throw ValidationException::withMessages(['answerer_id' => self::line('virtualBroker.errors.answerer')]);
        }

        $before = $this->settings($brokerage);
        $brokerage->forceFill(['virtual_broker' => ['answerer_id' => $answererId, 'reply_hours' => $replyHours]])->save();
        $this->audit->record('virtual_broker.settings', 'organisation', (string) $brokerage->id, ['before' => $before, 'after' => $this->settings($brokerage)], $actor, $brokerage->id);
    }

    /**
     * The agent's side: the brokerage, whether a manual is in, and their questions.
     *
     * @return array{brokerage: string, manual: bool, reply_hours: int, questions: list<QuestionRow>}
     */
    public function forAgent(User $agent): array
    {
        $brokerage = $this->brokerageOfAgent($agent);

        return [
            'brokerage' => $brokerage->name,
            'manual' => $this->knowledge->current($brokerage) !== null,
            'reply_hours' => $this->settings($brokerage)['reply_hours'],
            'questions' => array_values(BrokerQuestion::query()->where('user_id', $agent->id)->where('organisation_id', $brokerage->id)
                ->with(['answerer:id,name', 'deal:id,uuid,property', 'user:id,name'])->latest('id')->limit(50)->get()->map(self::row(...))->all()),
        ];
    }

    /** An agent's own question. */
    public function ownQuestion(User $agent, string $uuid): BrokerQuestion
    {
        return BrokerQuestion::query()->where('user_id', $agent->id)->where('uuid', $uuid)->with(['answerer:id,name', 'deal:id,uuid,property', 'user:id,name'])->firstOrFail();
    }

    /** A question to the broker's brokerage. */
    public function brokerageQuestion(User $broker, string $uuid): BrokerQuestion
    {
        // The whole agent (their language and channels) for the answer's notification.
        return BrokerQuestion::query()->where('organisation_id', $this->brokerageOfBroker($broker)->id)->where('uuid', $uuid)
            ->with(['answerer:id,name', 'deal:id,uuid,property', 'user', 'organisation'])->firstOrFail();
    }

    /**
     * The broker's side (web screen 34): knowledge, conflicts, settings, questions and stats.
     *
     * @return array{brokerage: string, manual: array{id: string, version: int, file_name: string, pages: int, passages: int, topics: list<string>, uploaded_at: string|null, uploaded_by: string|null}|null, versions: list<array{id: string, version: int, file_name: string, uploaded_at: string|null, current: bool}>, conflicts: list<array{rule: string, citation: string, url: string|null, page: int|null, excerpt: string, rule_text: string}>, faq: list<array{id: int, question: string, answer: string, added_at: string|null}>, settings: array{answerer_id: int|null, reply_hours: int, brokers: list<array{id: int, name: string}>}, waiting: list<QuestionRow>, answered: list<QuestionRow>, stats: array{answered_by_ai: int, sent_to_broker: int, reply_minutes: int|null}}
     */
    public function forBroker(User $broker): array
    {
        $brokerage = $this->brokerageOfBroker($broker);
        $manual = $this->knowledge->current($brokerage);
        $rows = static fn (string ...$statuses): array => array_values(BrokerQuestion::query()->where('organisation_id', $brokerage->id)->whereIn('status', $statuses)
            ->with(['answerer:id,name', 'deal:id,uuid,property', 'user:id,name'])->orderBy($statuses === [BrokerQuestion::WAITING] ? 'due_at' : 'answered_at', $statuses === [BrokerQuestion::WAITING] ? 'asc' : 'desc')
            ->limit(50)->get()->map(self::row(...))->all());
        $since = CarbonImmutable::now()->subDays(30);
        $recent = BrokerQuestion::query()->where('organisation_id', $brokerage->id)->where('created_at', '>=', $since)->get(['status', 'escalated_at', 'answered_at']);
        $replies = $recent->filter(static fn (BrokerQuestion $q): bool => $q->escalated_at !== null && $q->answered_at !== null)
            ->map(static fn (BrokerQuestion $q): float => (float) $q->escalated_at?->diffInMinutes($q->answered_at, true));

        return [
            'brokerage' => $brokerage->name,
            'manual' => $manual === null ? null : [
                'id' => $manual->uuid, 'version' => $manual->version, 'file_name' => $manual->file_name, 'pages' => $manual->pages, 'passages' => $manual->passages,
                'topics' => $manual->topics, 'uploaded_at' => $manual->created_at?->toIso8601String(), 'uploaded_by' => $manual->uploader?->name,
            ],
            'versions' => array_values(PolicyManual::query()->where('organisation_id', $brokerage->id)->latest('version')->limit(20)->get()
                ->map(static fn (PolicyManual $m): array => ['id' => $m->uuid, 'version' => $m->version, 'file_name' => $m->file_name, 'uploaded_at' => $m->created_at?->toIso8601String(), 'current' => $m->status === PolicyManual::CURRENT])->all()),
            'conflicts' => array_map(static fn (array $c): array => ['rule' => $c['rule'], 'citation' => $c['citation'], 'url' => $c['url'], 'page' => $c['page'], 'excerpt' => $c['excerpt'], 'rule_text' => $c['rule_text']], $manual->conflicts ?? []),
            'faq' => array_values(PolicyPassage::query()->where('organisation_id', $brokerage->id)->where('kind', PolicyPassage::FAQ)->latest('id')->limit(100)->get()
                ->map(static fn (PolicyPassage $p): array => ['id' => $p->id, 'question' => (string) $p->heading, 'answer' => $p->content, 'added_at' => $p->created_at?->toIso8601String()])->all()),
            'settings' => [
                ...$this->settings($brokerage),
                'brokers' => array_values($this->memberships->holdersIn(Role::Broker, $brokerage)->sortBy('name')->map(static fn (User $u): array => ['id' => $u->id, 'name' => (string) $u->name])->all()),
            ],
            'waiting' => $rows(BrokerQuestion::WAITING),
            'answered' => $rows(BrokerQuestion::CONFIRMED, BrokerQuestion::CORRECTED),
            'stats' => [
                'answered_by_ai' => $recent->where('status', BrokerQuestion::ANSWERED)->count(),
                'sent_to_broker' => $recent->whereNotNull('escalated_at')->count(),
                'reply_minutes' => $replies->isEmpty() ? null : (int) round((float) $replies->avg()),
            ],
        ];
    }

    /** @return QuestionRow */
    public static function row(BrokerQuestion $q): array
    {
        return [
            'id' => $q->uuid, 'question' => $q->question, 'answer' => $q->answer, 'confidence' => $q->confidence,
            'sources' => array_map(self::source(...), $q->sources),
            'status' => $q->status, 'reason' => $q->reason, 'escalated_at' => $q->escalated_at?->toIso8601String(), 'due_at' => $q->due_at?->toIso8601String(),
            'broker_answer' => $q->broker_answer, 'broker' => $q->answerer?->name, 'answered_at' => $q->answered_at?->toIso8601String(),
            'added_to_knowledge' => $q->added_to_knowledge, 'agent' => (string) $q->user->name,
            'deal' => $q->deal === null ? null : ['id' => $q->deal->id, 'uuid' => $q->deal->uuid, 'property' => $q->deal->property],
            'created_at' => $q->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<array-key, mixed>  $hit
     * @return Source
     */
    private static function source(array $hit): array
    {
        $text = static fn (string $k): ?string => is_string($hit[$k] ?? null) ? $hit[$k] : null;

        return [
            'kind' => $text('kind') ?? 'manual', 'title' => $text('title') ?? '', 'citation' => $text('citation'),
            'page' => is_int($hit['page'] ?? null) ? $hit['page'] : null, 'url' => $text('url'), 'effective_from' => $text('effective_from'), 'excerpt' => $text('excerpt') ?? '',
        ];
    }

    /**
     * Claude when the keys are in; otherwise the Simulator quotes the best sources.
     *
     * @param  list<Hit>  $hits
     * @return array{0: string, 1: 'high'|'medium'|'low', 2: list<Hit>, 3: bool, 4: string}
     */
    private function answer(string $question, array $hits, Organisation $brokerage, string $state, string $language): array
    {
        $bedrock = $hits === [] ? null : $this->bedrock->client(30);

        if ($bedrock !== null) {
            try {
                $reply = (new ClaudePolicyAnswerer($bedrock['client'], $bedrock['model']))->answer($question, $hits, $brokerage->name, $state, $language);

                return [$reply['answer'], $reply['confidence'], $reply['sources'], $reply['needs_broker'], 'ai'];
            } catch (Throwable $e) {
                Log::warning('virtual_broker.failed', ['error' => $e->getMessage()]);
            }
        }

        if ($hits === []) {
            return [self::line('virtualBroker.sim.none', ['brokerage' => $brokerage->name], $language), 'low', [], true, 'simulator'];
        }

        $best = $hits[0]['score'];
        $confidence = $best >= 0.6 ? 'high' : ($best >= 0.34 ? 'medium' : 'low');
        $used = array_values(array_filter($hits, static fn (array $h): bool => $h['score'] >= max(0.34, $best - 0.2)));
        $used = array_slice($used === [] ? [$hits[0]] : $used, 0, 2);
        $parts = [];

        foreach ($used as $hit) {
            $from = match ($hit['kind']) {
                'rule' => self::line('virtualBroker.sim.rule', ['citation' => $hit['citation'] ?? ''], $language),
                'faq' => self::line('virtualBroker.sim.faq', [], $language),
                default => $hit['page'] !== null ? self::line('virtualBroker.sim.page', ['page' => (string) $hit['page']], $language) : self::line('virtualBroker.sim.manual', [], $language),
            };
            $parts[] = $from.' '.self::sentences($hit['excerpt'], 2);
        }

        if (($hits[0]['trec_wins'] ?? false) === true) {
            array_unshift($parts, self::line('virtualBroker.sim.trecWins', [], $language));
        }

        return [implode("\n\n", $parts), $confidence, $used, false, 'simulator'];
    }

    /**
     * When the manual passages found contradict a TREC rule, the rule goes first.
     *
     * @param  list<Hit>  $hits
     * @return list<Hit>
     */
    private function withTexasFirst(Organisation $brokerage, array $hits): array
    {
        $manual = $this->knowledge->current($brokerage);
        $best = $hits[0]['score'] ?? 0.0;
        // The manual passage the answer would quote (the best one close to the top).
        $hit = array_values(array_filter($hits, static fn (array $h): bool => $h['kind'] === 'manual' && $h['score'] >= max(0.34, $best - 0.2)))[0] ?? null;

        // Only when that passage is one that contradicts TREC.
        if ($hit !== null) {
            foreach ($manual->conflicts ?? [] as $conflict) {
                if ($hit['position'] === $conflict['position']) {
                    $rules = $this->search->stateRules(($brokerage->states ?? [])[0] ?? 'TX');
                    $rule = $rules[$conflict['rule']] ?? null;

                    if ($rule !== null) {
                        $first = [...PolicySearch::ruleHit($rule, max($hit['score'], 0.6)), 'trec_wins' => true];

                        return [$first, ...array_values(array_filter($hits, static fn (array $h): bool => $h['id'] !== $first['id']))];
                    }
                }
            }
        }

        return $hits;
    }

    /** @return list<User> */
    private function answerers(Organisation $brokerage): array
    {
        $brokers = $this->memberships->holdersIn(Role::Broker, $brokerage);
        $chosen = $this->settings($brokerage)['answerer_id'];
        $one = $chosen === null ? null : $brokers->firstWhere('id', $chosen);

        return $one instanceof User ? [$one] : array_values($brokers->all());
    }

    private function brokerageFor(User $user, Role $role): Organisation
    {
        foreach ($this->memberships->of($user) as $membership) {
            if ($membership->role === $role && $membership->organisation->type === OrganisationType::Brokerage) {
                return $membership->organisation;
            }
        }

        throw new NotFoundHttpException;
    }

    private static function sentences(string $text, int $count): string
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [$text];

        return implode(' ', array_slice($parts, 0, $count));
    }

    private static function normalise(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, array $replace = [], ?string $language = null): string
    {
        $line = __($key, $replace, $language);

        return is_string($line) ? $line : $key;
    }
}
