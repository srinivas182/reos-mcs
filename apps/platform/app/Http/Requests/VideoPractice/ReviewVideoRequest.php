<?php

declare(strict_types=1);

namespace App\Http\Requests\VideoPractice;

use App\VideoPractice\VideoPractices;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A recording to review (A14.1b): its kind, the words and its length. The video itself is never sent. */
final class ReviewVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(VideoPractices::KINDS)],
            'transcript' => ['required', 'string', 'max:5000'],
            // A little over two minutes, for the moment it takes to stop.
            'duration_seconds' => ['required', 'integer', 'min:1', 'max:'.(VideoPractices::MAX_SECONDS + 10)],
        ];
    }

    /** @return 'listing_pitch'|'video_intro' */
    public function kind(): string
    {
        return $this->string('kind')->toString() === 'video_intro' ? 'video_intro' : 'listing_pitch';
    }
}
