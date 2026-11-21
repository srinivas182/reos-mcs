<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\Enums\OnboardingPath;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a person chose while getting started, and the plan it builds
 * ("Plan built", mobile journey screen 9).
 *
 * @property User $resource
 */
final class OnboardingResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $state = $user->target_state === null ? null : State::query()->find($user->target_state);
        $exam = $user->exam_month;

        return [
            /**
             * The card chosen on "Choose your path"; null before choosing.
             *
             * @var OnboardingPath|null
             */
            'path' => $user->onboarding_path === null ? null : OnboardingPath::from($user->onboarding_path),
            'target_state' => $user->target_state,
            /** The month they plan to take the exam (YYYY-MM); null means "Not sure". */
            'exam_month' => $exam?->format('Y-m'),
            'minutes_per_day' => $user->minutes_per_day === null ? null : (int) $user->minutes_per_day,
            /** Daily reminder (HH:MM), or null for none. */
            'reminder_time' => $user->reminder_time === null ? null : substr((string) $user->reminder_time, 0, 5),
            /** The 60-second setup is done, so the chooser and setup never show again. */
            'completed' => $user->onboarded_at !== null,
            'plan' => [
                /** Days until the start of the exam month. */
                'days_to_exam' => $exam === null ? null : (int) max(0, (int) now()->startOfDay()->diffInDays($exam->copy()->startOfMonth(), false)),
                /** Qualifying education the state asks for (180 in Texas). */
                'qualifying_hours' => $state?->qualifying_hours,
            ],
        ];
    }
}
