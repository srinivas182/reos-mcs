<?php

declare(strict_types=1);

namespace App\Ask;

use App\Models\User;
use App\Services\Ai\AiCredits;
use App\Services\Ai\AiSettings;

/**
 * My package and AI help (A10.1, screen k10): answers from the course are
 * always free; answers beyond it cost credits, taken from the candidate's
 * free monthly credits, then their sponsor's pool, then credits they
 * bought (decided 23 November 2026).
 *
 * @phpstan-type Package array{candidate: bool, beyond_course: bool, free_left: int|null, free_monthly: int|null, sponsor: string|null, credits_left: int, cost: int}
 */
final readonly class AskPackage
{
    public function __construct(private AiCredits $credits, private AiSettings $settings) {}

    /** @return Package */
    public function for(User $user): array
    {
        $cost = max(0, $this->settings->cost('general_answer'));
        $candidate = $this->credits->forCandidate($user);
        $summary = $this->credits->summary($user);

        if (! $candidate) {
            $left = $this->credits->left($user);

            return ['candidate' => false, 'beyond_course' => $left >= $cost, 'free_left' => null, 'free_monthly' => null, 'sponsor' => null, 'credits_left' => $left, 'cost' => $cost];
        }

        return [
            'candidate' => true,
            'beyond_course' => $this->credits->candidatePayer($user, $cost) !== null,
            'free_left' => $summary['monthly'],
            'free_monthly' => $summary['allowance'],
            'sponsor' => $this->credits->sponsorFor($user, $cost)?->name,
            'credits_left' => $summary['topup'],
            'cost' => $cost,
        ];
    }
}
