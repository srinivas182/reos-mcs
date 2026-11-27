<?php

declare(strict_types=1);

namespace App\RolePlay;

use App\Authorization\Role;
use App\Models\RolePlay;
use App\Models\SkillAssessment;
use App\Models\User;
use App\Services\Ai\Bedrock;
use App\Services\Authorization\ActiveRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Role-play and the skills assessment (A14.1a, ADR 0060; agent screen 7).
 *
 * The agent picks a type and a persona; the persona opens, the agent
 * answers by voice or typing, and the persona pushes back — Claude plays
 * it when configured, otherwise REOS's script. After four answers (two in
 * an assessment round) the persona agrees to the next step or declines,
 * and the agent gets a score out of 100 on five parts and **three fixes**,
 * each with a line to try and a lesson. The agent can also finish early.
 *
 * The **skills assessment** is five short rounds, one per type. When all
 * five are scored, the weakest types come first: the 90-day plan gets a
 * weekly practice of the weakest skill, and course picks (A14.2) use them.
 *
 * Practice is private to the agent and carries no organisation.
 *
 * @phpstan-import-type Scores from RolePlayScorer
 * @phpstan-import-type Fix from RolePlayScorer
 * @phpstan-import-type SkillType from Scenarios
 * @phpstan-import-type PersonaCode from Scenarios
 *
 * @phpstan-type Turn array{role: 'persona'|'agent', text: string}
 * @phpstan-type Session array{id: string, type: SkillType, persona: PersonaCode, kind: 'practice'|'assessment', round: int|null, status: 'active'|'scored', turns: list<Turn>, turns_left: int, score: int|null, scores: Scores|null, fixes: list<Fix>, outcome: 'won'|'lost'|null, engine: 'claude'|'script', created_at: string}
 * @phpstan-type Summary array{id: string, type: SkillType, persona: PersonaCode, kind: 'practice'|'assessment', status: 'active'|'scored', score: int|null, created_at: string}
 * @phpstan-type Assessment array{id: string, done: int, total: int, next: SkillType|null, completed: bool, results: list<array{type: SkillType, score: int}>, weakest: list<SkillType>, completed_at: string|null}
 * @phpstan-type Overview array{types: list<array{type: SkillType, personas: list<PersonaCode>, best: int|null}>, recent: list<Summary>, assessment: Assessment|null}
 */
final readonly class RolePlays
{
    public const RECENT = 10;

    /** An ask that earns this many points (of 20) wins the next step. */
    public const WON = 10;

    public function __construct(private ActiveRole $roles, private Bedrock $bedrock, private RolePlayScorer $scorer) {}

    /** @return Overview */
    public function overview(User $user): array
    {
        $this->allow($user);
        $best = RolePlay::query()->where('user_id', $user->id)->where('status', 'scored')
            ->selectRaw('type, max(score) as best')->groupBy('type')->pluck('best', 'type');

        return [
            'types' => array_map(static fn (string $type): array => ['type' => $type, 'personas' => Scenarios::personas($type), 'best' => is_numeric($best[$type] ?? null) ? (int) $best[$type] : null], Scenarios::TYPES),
            'recent' => array_values(array_map(fn (RolePlay $p): array => $this->summary($p), RolePlay::query()->where('user_id', $user->id)->latest('id')->limit(self::RECENT)->get()->all())),
            'assessment' => ($latest = $this->latestAssessment($user)) === null ? null : $this->assessmentView($latest),
        ];
    }

    /** Starts a practice with this persona; the persona speaks first. */
    public function start(User $user, string $type, string $persona, ?SkillAssessment $assessment = null): RolePlay
    {
        $this->allow($user);

        if (! Scenarios::isPersona($type, $persona)) {
            throw ValidationException::withMessages(['persona' => __('roleplay.errors.persona')]);
        }

        $language = $user->locale->value === 'es' ? 'es' : 'en';

        return RolePlay::query()->create([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'skill_assessment_id' => $assessment?->id,
            'type' => $type,
            'persona' => $persona,
            'language' => $language,
            'turns' => [['role' => 'persona', 'text' => Scenarios::line($persona, 0, $language)]],
            'status' => 'active',
        ]);
    }

    /**
     * Starts the skills assessment, or its next round; a finished one
     * starts over. Returns the round to play.
     */
    public function assess(User $user): RolePlay
    {
        $this->allow($user);
        $assessment = SkillAssessment::query()->where('user_id', $user->id)->whereNull('completed_at')->latest('id')->first()
            ?? SkillAssessment::query()->create(['uuid' => (string) Str::uuid7(), 'user_id' => $user->id]);
        $rounds = RolePlay::query()->where('skill_assessment_id', $assessment->id)->get();
        $open = $rounds->firstWhere('status', 'active');

        if ($open instanceof RolePlay) {
            return $open;
        }

        $next = $this->nextType($rounds->pluck('type')->all());

        return $this->start($user, $next ?? Scenarios::TYPES[0], Scenarios::personas($next ?? Scenarios::TYPES[0])[0], $assessment);
    }

    /** @return Session */
    public function show(User $user, string $uuid): array
    {
        return $this->view($this->find($user, $uuid));
    }

    /**
     * The agent's answer; the persona replies. After the last answer the
     * persona agrees or declines and the practice is scored.
     *
     * @return Session
     */
    public function say(User $user, string $uuid, string $text): array
    {
        $play = $this->find($user, $uuid);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($play->status !== 'active') {
            throw ValidationException::withMessages(['text' => __('roleplay.errors.finished')]);
        }

        if ($text === '') {
            throw ValidationException::withMessages(['text' => __('roleplay.errors.empty')]);
        }

        $turns = $play->turns;
        $turns[] = ['role' => 'agent', 'text' => $text];
        $answers = count(array_filter($turns, static fn (array $t): bool => $t['role'] === 'agent'));
        $last = $answers >= $this->limit($play);
        $turns[] = ['role' => 'persona', 'text' => $this->reply($play, $turns, $answers, $last)];
        $play->forceFill(['turns' => $turns])->save();

        return $last ? $this->finish($user, $uuid) : $this->view($play);
    }

    /**
     * Scores the practice: Claude when configured, otherwise REOS's
     * scoring; exactly three fixes either way.
     *
     * @return Session
     */
    public function finish(User $user, string $uuid): array
    {
        $play = $this->find($user, $uuid);

        if ($play->status === 'scored') {
            return $this->view($play);
        }

        $said = $this->said($play->turns);

        if ($said === []) {
            throw ValidationException::withMessages(['text' => __('roleplay.errors.nothingSaid')]);
        }

        [$review, $engine] = $this->review($play, $said);

        DB::transaction(function () use ($play, $review, $engine): void {
            $play->forceFill(['status' => 'scored', 'score' => $review['score'], 'scores' => $review['scores'], 'fixes' => $review['fixes'], 'engine' => $engine, 'finished_at' => now()])->save();

            if ($play->skill_assessment_id !== null) {
                $this->complete($play->skill_assessment_id);
            }
        });

        return $this->view($play);
    }

    /** The agent's last finished assessment, or the one under way. */
    public function latestAssessment(User $user): ?SkillAssessment
    {
        return SkillAssessment::query()->where('user_id', $user->id)->latest('id')->first();
    }

    /**
     * The lessons behind the three fixes of the agent's latest scored
     * practice, for course picks (A14.2).
     *
     * @return list<string>
     */
    public function latestFixes(User $user): array
    {
        $play = RolePlay::query()->where('user_id', $user->id)->where('status', 'scored')->latest('finished_at')->latest('id')->first();

        return array_map(static fn (array $f): string => $f['lesson'], $play->fixes ?? []);
    }

    /**
     * The weakest skills from the last finished assessment, weakest first.
     *
     * @return list<string>
     */
    public function weakest(User $user): array
    {
        return SkillAssessment::query()->where('user_id', $user->id)->whereNotNull('completed_at')->latest('completed_at')->first()->weakest ?? [];
    }

    /** @return Session */
    public function view(RolePlay $play): array
    {
        $round = null;

        if ($play->skill_assessment_id !== null) {
            $index = array_search($play->type, Scenarios::TYPES, true);
            $round = is_int($index) ? $index + 1 : null;
        }

        $answers = count($this->said($play->turns));
        $scores = $play->scores;

        return [
            'id' => $play->uuid,
            'type' => $play->type,
            'persona' => $play->persona,
            'kind' => $play->skill_assessment_id === null ? 'practice' : 'assessment',
            'round' => $round,
            'status' => $play->status === 'scored' ? 'scored' : 'active',
            'turns' => $play->turns,
            'turns_left' => $play->status === 'scored' ? 0 : max(0, $this->limit($play) - $answers),
            'score' => $play->score,
            'scores' => $scores,
            'fixes' => $play->fixes ?? [],
            'outcome' => $scores === null ? null : ($scores['ask'] >= self::WON ? 'won' : 'lost'),
            'engine' => $play->engine === 'claude' ? 'claude' : 'script',
            'created_at' => ($play->created_at ?? now())->toIso8601String(),
        ];
    }

    /** @return Assessment */
    public function assessmentView(SkillAssessment $assessment): array
    {
        $rounds = RolePlay::query()->where('skill_assessment_id', $assessment->id)->where('status', 'scored')->pluck('type')->all();
        $done = array_values(array_unique(array_filter($rounds, static fn (mixed $t): bool => is_string($t))));

        return [
            'id' => $assessment->uuid,
            'done' => count($done),
            'total' => count(Scenarios::TYPES),
            'next' => $assessment->completed_at === null ? $this->nextType($done) : null,
            'completed' => $assessment->completed_at !== null,
            'results' => $assessment->results ?? [],
            'weakest' => $assessment->weakest ?? [],
            'completed_at' => $assessment->completed_at?->toIso8601String(),
        ];
    }

    /** @return Summary */
    private function summary(RolePlay $play): array
    {
        return [
            'id' => $play->uuid,
            'type' => $play->type,
            'persona' => $play->persona,
            'kind' => $play->skill_assessment_id === null ? 'practice' : 'assessment',
            'status' => $play->status === 'scored' ? 'scored' : 'active',
            'score' => $play->score,
            'created_at' => ($play->created_at ?? now())->toIso8601String(),
        ];
    }

    /** With every round scored: the results and the two weakest types. */
    private function complete(int $assessmentId): void
    {
        $assessment = SkillAssessment::query()->lockForUpdate()->findOrFail($assessmentId);
        $scores = RolePlay::query()->where('skill_assessment_id', $assessment->id)->where('status', 'scored')->pluck('score', 'type');

        if ($assessment->completed_at !== null || count(array_intersect(Scenarios::TYPES, $scores->keys()->all())) < count(Scenarios::TYPES)) {
            return;
        }

        $results = array_map(static fn (string $type): array => ['type' => $type, 'score' => is_numeric($scores[$type] ?? null) ? (int) $scores[$type] : 0], Scenarios::TYPES);
        $order = $results;
        usort($order, static fn (array $a, array $b): int => [$a['score'], array_search($a['type'], Scenarios::TYPES, true)] <=> [$b['score'], array_search($b['type'], Scenarios::TYPES, true)]);

        $assessment->forceFill(['results' => $results, 'weakest' => array_map(static fn (array $r): string => $r['type'], array_slice($order, 0, 2)), 'completed_at' => now()])->save();
    }

    /**
     * @param  array<mixed>  $done
     * @return SkillType|null
     */
    private function nextType(array $done): ?string
    {
        foreach (Scenarios::TYPES as $type) {
            if (! in_array($type, $done, true)) {
                return $type;
            }
        }

        return null;
    }

    private function limit(RolePlay $play): int
    {
        return $play->skill_assessment_id === null ? Scenarios::PRACTICE_TURNS : Scenarios::ROUND_TURNS;
    }

    /**
     * The persona's next line: Claude when configured, otherwise the script
     * (the next pushback, or at the end won when the agent asked for the
     * next step).
     *
     * @param  list<Turn>  $turns
     */
    private function reply(RolePlay $play, array $turns, int $answers, bool $last): string
    {
        $bedrock = $this->bedrock->client(15);

        if ($bedrock !== null) {
            try {
                return (new ClaudeRolePlayer($bedrock['client'], $bedrock['model']))->reply($play->persona, $turns, $last, $play->language);
            } catch (Throwable $e) {
                Log::warning('roleplay.reply_failed', ['role_play' => $play->id, 'error' => $e->getMessage()]);
            }
        }

        if ($last) {
            $asked = $this->scorer->score($play->persona, $this->said($turns), $play->language)['scores']['ask'] >= self::WON;

            return Scenarios::line($play->persona, $asked ? 'won' : 'lost', $play->language);
        }

        return Scenarios::line($play->persona, min($answers, Scenarios::LINES - 1), $play->language);
    }

    /**
     * @param  list<string>  $said
     * @return array{0: array{score: int, scores: Scores, fixes: list<Fix>}, 1: 'claude'|'script'}
     */
    private function review(RolePlay $play, array $said): array
    {
        $bedrock = $this->bedrock->client(25);

        if ($bedrock !== null) {
            try {
                return [(new ClaudeRolePlayCoach($bedrock['client'], $bedrock['model']))->review($play->type, $play->persona, $play->turns, $play->language), 'claude'];
            } catch (Throwable $e) {
                Log::warning('roleplay.review_failed', ['role_play' => $play->id, 'error' => $e->getMessage()]);
            }
        }

        return [$this->scorer->score($play->persona, $said, $play->language), 'script'];
    }

    /**
     * @param  list<Turn>  $turns
     * @return list<string>
     */
    private function said(array $turns): array
    {
        return array_values(array_map(static fn (array $t): string => $t['text'], array_filter($turns, static fn (array $t): bool => $t['role'] === 'agent')));
    }

    private function find(User $user, string $uuid): RolePlay
    {
        $this->allow($user);

        return RolePlay::query()->where('user_id', $user->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException;
    }

    private function allow(User $user): void
    {
        abort_unless($this->roles->for($user)?->role === Role::Agent, 404);
    }
}
