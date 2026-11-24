<?php

declare(strict_types=1);

namespace App\Jobs;

use App\ContractReview\ContractReviews;
use App\Models\ContractReview;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** A contract review, in the background (A12.2): about 20–40 seconds with Claude. */
final class ReviewContract implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public readonly int $reviewId) {}

    public function handle(ContractReviews $reviews): void
    {
        $review = ContractReview::query()->find($this->reviewId);

        if ($review !== null) {
            $reviews->run($review);
        }
    }
}
