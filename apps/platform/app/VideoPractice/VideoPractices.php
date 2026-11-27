<?php

declare(strict_types=1);

namespace App\VideoPractice;

use App\Authorization\Role;
use App\Compliance\ComplianceEngine;
use App\Models\User;
use App\Models\VideoPractice;
use App\Services\Ai\Bedrock;
use App\Services\Authorization\ActiveRole;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Video practice with an AI review (A14.1b, ADR 0061).
 *
 * The agent records a listing pitch or a video intro (up to two minutes)
 * on the phone or in the browser. The video never leaves the device: REOS
 * gets the words — recognised on the device, which the agent can correct —
 * and the length. The review gives the pace (words a minute), the filler
 * words, a score out of 100 on five parts and three fixes (Claude when
 * configured, REOS's review otherwise), plus what the compliance engine
 * would flag if they posted it. Private to the agent.
 *
 * @phpstan-import-type Scores from VideoReviewer
 * @phpstan-import-type Fix from VideoReviewer
 * @phpstan-import-type Filler from VideoReviewer
 *
 * @phpstan-type Kind 'listing_pitch'|'video_intro'
 * @phpstan-type Review array{id: string, kind: Kind, transcript: string, duration_seconds: int, words: int, wpm: int, pace: 'slow'|'good'|'fast', fillers: list<Filler>, score: int, scores: Scores, fixes: list<Fix>, compliance: list<array{code: string, phrase: string|null}>, engine: 'claude'|'script', created_at: string}
 * @phpstan-type Summary array{id: string, kind: Kind, score: int, wpm: int, duration_seconds: int, created_at: string}
 * @phpstan-type Overview array{kinds: list<Kind>, max_seconds: int, recent: list<Summary>}
 */
final readonly class VideoPractices
{
    public const KINDS = ['listing_pitch', 'video_intro'];

    public const MAX_SECONDS = 120;

    /** Too short to review: fewer words or seconds than this. */
    public const MIN_WORDS = 20;

    public const MIN_SECONDS = 10;

    public const RECENT = 10;

    public function __construct(private ActiveRole $roles, private Bedrock $bedrock, private VideoReviewer $reviewer, private ComplianceEngine $engine) {}

    /** @return Overview */
    public function overview(User $user): array
    {
        $this->allow($user);

        return [
            'kinds' => self::KINDS,
            'max_seconds' => self::MAX_SECONDS,
            'recent' => array_values(array_map(fn (VideoPractice $v): array => $this->summary($v), VideoPractice::query()->where('user_id', $user->id)->latest('id')->limit(self::RECENT)->get()->all())),
        ];
    }

    /**
     * Reviews a recording from its words and length.
     *
     * @param  Kind  $kind
     * @return Review
     */
    public function review(User $user, string $kind, string $transcript, int $seconds): array
    {
        $this->allow($user);
        $transcript = trim((string) preg_replace('/\s+/u', ' ', $transcript));
        $language = $user->locale->value === 'es' ? 'es' : 'en';
        $measures = $this->reviewer->measure($transcript, $seconds, $language);

        if ($measures['words'] < self::MIN_WORDS || $seconds < self::MIN_SECONDS) {
            throw ValidationException::withMessages(['transcript' => __('videopractice.errors.short')]);
        }

        [$review, $engine] = $this->score($kind, $transcript, $seconds, $measures, $language);
        $issues = $this->engine->check($user, ['channel' => 'video', 'text' => $transcript])['issues'];

        $practice = VideoPractice::query()->create([
            'uuid' => (string) Str::uuid7(),
            'user_id' => $user->id,
            'kind' => $kind,
            'language' => $language,
            'transcript' => $transcript,
            'duration_seconds' => $seconds,
            'words' => $measures['words'],
            'wpm' => $measures['wpm'],
            'fillers' => $measures['fillers'],
            'score' => $review['score'],
            'scores' => $review['scores'],
            'fixes' => $review['fixes'],
            'compliance' => array_map(static fn (array $i): array => ['code' => $i['code'], 'phrase' => $i['phrase']], $issues),
            'engine' => $engine,
        ]);

        return $this->view($practice);
    }

    /** @return Review */
    public function show(User $user, string $uuid): array
    {
        $this->allow($user);

        return $this->view(VideoPractice::query()->where('user_id', $user->id)->where('uuid', $uuid)->first() ?? throw new NotFoundHttpException);
    }

    /** @return Review */
    public function view(VideoPractice $v): array
    {
        [$low, $high] = VideoReviewer::PACE;

        return [
            'id' => $v->uuid,
            'kind' => $v->kind,
            'transcript' => $v->transcript,
            'duration_seconds' => $v->duration_seconds,
            'words' => $v->words,
            'wpm' => $v->wpm,
            'pace' => $v->wpm < $low ? 'slow' : ($v->wpm > $high ? 'fast' : 'good'),
            'fillers' => $v->fillers,
            'score' => $v->score,
            'scores' => $v->scores,
            'fixes' => $v->fixes,
            'compliance' => $v->compliance,
            'engine' => $v->engine === 'claude' ? 'claude' : 'script',
            'created_at' => ($v->created_at ?? now())->toIso8601String(),
        ];
    }

    /** @return Summary */
    private function summary(VideoPractice $v): array
    {
        return ['id' => $v->uuid, 'kind' => $v->kind, 'score' => $v->score, 'wpm' => $v->wpm, 'duration_seconds' => $v->duration_seconds, 'created_at' => ($v->created_at ?? now())->toIso8601String()];
    }

    /**
     * @param  array{words: int, wpm: int, fillers: list<Filler>}  $measures
     * @return array{0: array{score: int, scores: Scores, fixes: list<Fix>}, 1: 'claude'|'script'}
     */
    private function score(string $kind, string $transcript, int $seconds, array $measures, string $language): array
    {
        $bedrock = $this->bedrock->client(25);

        if ($bedrock !== null) {
            try {
                return [(new ClaudeVideoCoach($bedrock['client'], $bedrock['model']))->review($kind, $transcript, $seconds, $measures, $language), 'claude'];
            } catch (Throwable $e) {
                Log::warning('video_practice.review_failed', ['error' => $e->getMessage()]);
            }
        }

        return [$this->reviewer->review($transcript, $seconds, $measures, $language), 'script'];
    }

    private function allow(User $user): void
    {
        abort_unless($this->roles->for($user)?->role === Role::Agent, 404);
    }
}
