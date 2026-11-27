<?php

declare(strict_types=1);

namespace App\Studio\Celebrations;

use App\Compliance\ComplianceEngine;
use App\Compliance\ComplianceLog;
use App\Models\Celebration;
use App\Models\LicenceJourney;
use App\Models\Organisation;
use App\Models\StudioGeneration;
use App\Models\TestAttempt;
use App\Models\User;
use App\Practice\Readiness;
use App\Practice\WeeklyReports;
use App\Social\SocialPosts;
use App\Studio\StudioGenerator;
use App\Tenancy\Brand;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The candidate studio (B13.3, ADR 0059): free posts for a study streak, a
 * practice score and passing the exam, with captions and designs from
 * templates, the school's brand (or REOS's) and "Future agent" in place of
 * a broker. Every post goes through the compliance engine, which blocks
 * service advertising until the license is active (Tex. Occ. Code
 * §1101.351): no listings, prices, "call me to buy or sell" or brokerage
 * solicitation.
 *
 * @phpstan-import-type Issue from ComplianceEngine
 *
 * @phpstan-type Moment array{milestone: string, label: string, made: string|null}
 * @phpstan-type Made array{id: string, milestone: string, label: string, captions: list<array{tone: string, text: string}>, designs: list<array{layout: string, path: string, url: string}>, checks: list<array{caption: int, ok: bool, issues: list<Issue>}>}
 */
final readonly class CandidateStudio
{
    public const MILESTONES = ['exam_pass', 'study_streak', 'practice_score'];

    /** Study streaks worth a post. */
    public const STREAKS = [7, 14, 30, 60, 100];

    /** A practice exam score worth a post. */
    public const SCORE = 80;

    public function __construct(
        private StudioGenerator $generator,
        private Readiness $readiness,
        private ComplianceEngine $compliance,
        private ComplianceLog $log,
        private SocialPosts $posts,
    ) {}

    /**
     * The candidate's moments right now, and whether each was made into a post.
     *
     * @return list<Moment>
     */
    public function moments(User $candidate): array
    {
        $made = Celebration::query()->where('user_id', $candidate->id)->whereIn('milestone', self::MILESTONES)->pluck('uuid', 'subject')->all();
        $out = [];

        foreach ($this->found($candidate) as [$milestone, $subject, $label]) {
            $uuid = $made[$subject] ?? null;
            $out[] = ['milestone' => $milestone, 'label' => $label, 'made' => is_string($uuid) ? $uuid : null];
        }

        return $out;
    }

    /** @return Made */
    public function make(User $candidate, string $milestone): array
    {
        foreach ($this->found($candidate) as [$kind, $subject, $label]) {
            if ($kind !== $milestone) {
                continue;
            }

            $existing = Celebration::query()->where('user_id', $candidate->id)->where('subject', $subject)->first();

            if ($existing?->studio_generation_id !== null) {
                return $this->made($candidate, $existing);
            }

            $generation = $this->generator->celebrate($candidate, $milestone, $label, trim((string) $candidate->name).' · '.self::line('studio.candidate.futureAgent', $candidate), $this->colour($candidate));

            try {
                $celebration = DB::transaction(static fn (): Celebration => Celebration::query()->create([
                    'uuid' => (string) Str::uuid7(), 'user_id' => $candidate->id, 'milestone' => $milestone, 'subject' => $subject, 'label' => $label,
                    'studio_generation_id' => $generation->id, 'shown_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException) {
                $celebration = Celebration::query()->where('user_id', $candidate->id)->where('subject', $subject)->firstOrFail();
            }

            return $this->made($candidate, $celebration);
        }

        throw ValidationException::withMessages(['milestone' => self::line('studio.candidate.errors.notYet', $candidate)]);
    }

    /**
     * Posts the chosen caption and design to the candidate's accounts, through the same checks as any post.
     *
     * @param  list<array{connection: int, channel: string, id: string}>  $targets
     * @return array<string, mixed>
     */
    public function post(User $candidate, string $uuid, int $caption, int $design, string $text, array $targets): array
    {
        $celebration = Celebration::query()->where('user_id', $candidate->id)->where('uuid', $uuid)->whereIn('milestone', self::MILESTONES)->firstOrFail();
        $generation = StudioGeneration::query()->findOrFail($celebration->studio_generation_id);
        $path = $generation->designs[$design]['path'] ?? throw ValidationException::withMessages(['design' => self::line('studio.errors.caption', $candidate)]);
        $text = trim($text) !== '' ? $text : ($generation->captions[$caption]['text'] ?? '');
        $row = $this->posts->create($candidate, $text, [$path], null, $targets, 'now', null, null);
        $celebration->forceFill(['social_post_id' => DB::table('social_posts')->where('uuid', $row['id'])->value('id')])->save();

        return $row;
    }

    /**
     * Checks a caption the candidate edited before sharing it themselves.
     *
     * @return array{ok: bool, issues: list<Issue>}
     */
    public function check(User $candidate, string $text): array
    {
        $result = $this->compliance->check($candidate, ['channel' => 'post', 'text' => $text]);
        $this->log->record($candidate, 'post', $result['ok'] ? 'checked' : 'blocked', $result['issues'], subjectType: 'candidate_post');

        return $result;
    }

    /** @return Made */
    private function made(User $candidate, Celebration $celebration): array
    {
        $generation = StudioGeneration::query()->findOrFail($celebration->studio_generation_id);
        $link = static fn (string $path): string => URL::temporarySignedRoute('social.media', now()->addHours(SocialPosts::MEDIA_HOURS), ['path' => $path]);
        $checks = [];

        foreach ($generation->captions as $i => $c) {
            $result = $this->compliance->check($candidate, ['channel' => 'post', 'text' => $c['text']]);
            $checks[] = ['caption' => $i, 'ok' => $result['ok'], 'issues' => $result['issues']];
        }

        return [
            'id' => $celebration->uuid, 'milestone' => $celebration->milestone, 'label' => (string) $celebration->label,
            'captions' => array_map(static fn (array $c): array => ['tone' => (string) $c['tone'], 'text' => (string) $c['text']], $generation->captions),
            'designs' => array_map(static fn (array $d): array => ['layout' => (string) $d['layout'], 'path' => (string) $d['path'], 'url' => Storage::disk(SocialPosts::MEDIA_DISK)->exists((string) $d['path']) ? $link((string) $d['path']) : ''], $generation->designs),
            'checks' => $checks,
        ];
    }

    /**
     * What the candidate has done that's worth a post: [milestone, subject (made once), label].
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    private function found(User $candidate): array
    {
        $found = [];
        $journey = LicenceJourney::query()->where('user_id', $candidate->id)->whereNotNull('passed_on')->latest('passed_on')->first();

        if ($journey !== null) {
            $found[] = ['exam_pass', "exam_pass:{$journey->state_code}", self::line('studio.candidate.labels.exam_pass', $candidate, ['state' => $journey->state_code === 'TX' ? 'Texas' : $journey->state_code])];
        }

        $streak = $this->readiness->streak($candidate);
        $reached = array_values(array_filter(self::STREAKS, static fn (int $n): bool => $streak >= $n));

        if ($reached !== []) {
            $days = (int) end($reached);
            $found[] = ['study_streak', "study_streak:{$days}", self::line('studio.candidate.labels.study_streak', $candidate, ['days' => (string) $days])];
        }

        $best = 0;

        foreach (TestAttempt::query()->where('user_id', $candidate->id)->whereIn('kind', ['simulation', 'national', 'state'])->whereNotNull('submitted_at')->where('total', '>', 0)->get(['correct', 'total']) as $attempt) {
            $best = max($best, (int) floor(100 * (int) $attempt->correct / max(1, $attempt->total)));
        }

        if ($best >= self::SCORE) {
            $found[] = ['practice_score', "practice_score:{$best}", self::line('studio.candidate.labels.practice_score', $candidate, ['score' => (string) $best])];
        }

        return $found;
    }

    /** The candidate's school's color, else REOS's. */
    private function colour(User $candidate): string
    {
        $school = WeeklyReports::school($candidate);
        $organisation = $school === null ? null : Organisation::query()->find($school);

        return ($organisation === null ? null : Brand::of($organisation)->primary?->hex) ?? StudioGenerator::COLOUR;
    }

    /** @param  array<string, string>  $replace */
    private static function line(string $key, User $user, array $replace = []): string
    {
        $line = __($key, $replace, $user->locale->value);

        return is_string($line) ? $line : $key;
    }
}
