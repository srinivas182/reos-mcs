<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\OnboardingPath;
use App\Models\State;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * What a visitor chose on reos.com before having an account: their path, state
 * and free-quiz answers. It lives in the session (the phone keeps the same
 * draft on the device) and is saved to the account at the first sign-in or
 * sign-up, then forgotten.
 */
final readonly class WebDraft
{
    public const KEY = 'reos.onboarding';

    public function __construct(
        private Session $session,
        private Onboarding $onboarding,
        private FreeQuiz $quiz,
    ) {}

    public function path(): ?OnboardingPath
    {
        $path = $this->session->get(self::KEY.'.path');

        return is_string($path) ? OnboardingPath::tryFrom($path) : null;
    }

    public function choosePath(OnboardingPath $path): void
    {
        $this->session->put(self::KEY.'.path', $path->value);
    }

    public function state(): ?State
    {
        $code = $this->session->get(self::KEY.'.state');

        return is_string($code) ? State::query()->find($code) : null;
    }

    public function chooseState(State $state): void
    {
        $this->session->put(self::KEY.'.state', $state->code);
        // A new state means new questions.
        $this->session->forget(self::KEY.'.answers');
    }

    /** @return list<array{question_id: int, choice: int}> */
    public function answers(): array
    {
        $answers = $this->session->get(self::KEY.'.answers');

        if (! is_array($answers)) {
            return [];
        }

        $clean = [];

        foreach ($answers as $answer) {
            if (is_array($answer) && is_int($answer['question_id'] ?? null) && is_int($answer['choice'] ?? null)) {
                $clean[] = ['question_id' => $answer['question_id'], 'choice' => $answer['choice']];
            }
        }

        return $clean;
    }

    /** @param  list<array{question_id: int, choice: int}>  $answers */
    public function answer(array $answers): void
    {
        $this->session->put(self::KEY.'.answers', $answers);
    }

    public function isEmpty(): bool
    {
        return ! $this->session->has(self::KEY);
    }

    /** Save the draft to the account, then forget it. */
    public function attach(User $user): void
    {
        if ($this->isEmpty()) {
            return;
        }

        $path = $this->path();
        $state = $this->state();
        $answers = $this->answers();
        $this->session->forget(self::KEY);

        if ($path !== null || $state !== null) {
            // Someone who has already finished setting up keeps their choices.
            $this->onboarding->save($user, array_filter(
                ['path' => $user->onboarding_path === null ? $path : null, 'state' => $user->target_state === null ? $state : null],
                static fn (mixed $value): bool => $value !== null,
            ));
        }

        if ($answers !== []) {
            $this->quiz->record($user, $state, $answers);
        }
    }
}
