<?php

declare(strict_types=1);

namespace App\Http\Requests\Practice;

use App\Practice\Flashcards\Fsrs;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Flashcard reviews to apply (A9.2d): one from the web, or the phone's offline queue. */
final class FlashcardReviewsRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'reviews' => ['required', 'array', 'min:1', 'max:500'],
            'reviews.*.card_id' => ['required', 'integer'],
            'reviews.*.rating' => ['required', 'integer', 'between:'.Fsrs::AGAIN.','.Fsrs::GOOD],
            'reviews.*.reviewed_at' => ['required', 'date'],
            'reviews.*.client_id' => ['required', 'uuid', 'distinct'],
        ];
    }

    /** @return list<array{card_id: int, rating: int, reviewed_at: string, client_id: string}> */
    public function reviews(): array
    {
        $reviews = $this->validated('reviews');
        $out = [];

        foreach (is_array($reviews) ? $reviews : [] as $r) {
            if (is_array($r) && is_numeric($r['card_id'] ?? null) && is_numeric($r['rating'] ?? null) && is_string($r['reviewed_at'] ?? null) && is_string($r['client_id'] ?? null)) {
                $out[] = ['card_id' => (int) $r['card_id'], 'rating' => (int) $r['rating'], 'reviewed_at' => $r['reviewed_at'], 'client_id' => strtolower($r['client_id'])];
            }
        }

        return $out;
    }
}
