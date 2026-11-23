<?php

declare(strict_types=1);

namespace App\Practice;

use App\Authorization\Role;
use App\Models\TestAttempt;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Services\States\PersonStates;

/** Practice, readiness and the study plan are the candidate's (A8.3): anyone else gets a 404. */
final readonly class PracticeAccess
{
    public function __construct(private ActiveRole $roles, private Tests $tests, private ExamFormats $formats, private PersonStates $states) {}

    public function allow(User $user): void
    {
        abort_unless($this->roles->for($user)?->role === Role::Candidate, 404);
    }

    public function attempt(User $user, int $id): TestAttempt
    {
        $this->allow($user);

        return TestAttempt::query()->where('user_id', $user->id)->findOrFail($id);
    }

    /**
     * The practice hub (candidate screen 39, web Practice and exams): the
     * diagnostic and each topic quiz with the best and last score, and the
     * open attempt to carry on.
     *
     * @return list<array{kind: string, topic: string|null, questions: int, minutes: int|null, best: int|null, last: int|null, open: int|null, last_attempt: int|null}>
     */
    public function hub(User $user): array
    {
        TestAttempt::query()->where('user_id', $user->id)->whereNull('submitted_at')->whereNotNull('ends_at')->where('ends_at', '<', now())->get()->each(fn (TestAttempt $a): bool => $this->tests->expire($a));
        $attempts = TestAttempt::query()->where('user_id', $user->id)->orderBy('id')->get();
        $rows = [['diagnostic', null, DiagnosticBank::QUESTIONS, DiagnosticBank::MINUTES]];
        // The real exam's portions and the full simulation (A9.1).
        $state = $this->states->current($user);
        $national = $this->formats->portion($state, ExamFormats::NATIONAL);
        $portion = $this->formats->portion($state, ExamFormats::STATE);
        $rows[] = [TestAttempt::NATIONAL, null, $national['questions'], $national['minutes']];
        $rows[] = [TestAttempt::STATE, null, $portion['questions'], $portion['minutes']];
        $rows[] = [TestAttempt::SIMULATION, null, $national['questions'] + $portion['questions'], $national['minutes'] + $portion['minutes']];

        foreach (array_keys(DiagnosticBank::WEIGHTS) as $topic) {
            $rows[] = ['topic', $topic, Tests::TOPIC_QUESTIONS, null];
        }

        $hub = [];

        foreach ($rows as [$kind, $topic, $questions, $minutes]) {
            $mine = $attempts->filter(static fn (TestAttempt $a): bool => $a->kind === $kind && $a->topic === $topic);
            $done = $mine->whereNotNull('submitted_at');
            $best = $done->max('score');
            $last = $done->last();
            $open = $mine->whereNull('submitted_at')->last();
            $hub[] = [
                'kind' => $kind, 'topic' => $topic, 'questions' => $questions, 'minutes' => $minutes,
                'best' => is_numeric($best) ? (int) $best : null, 'last' => $last?->score, 'open' => $open?->id, 'last_attempt' => $last?->id,
            ];
        }

        return $hub;
    }
}
