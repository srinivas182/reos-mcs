<?php

declare(strict_types=1);

namespace App\Http\Requests\Mentoring;

use App\Mentoring\MentorProgram;
use App\Models\MentorSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A mentor's settings at the brokerage (A13.1a, broker screen 22). */
final class MentorSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'topics' => ['present', 'array', 'max:8'],
            'topics.*' => ['required', Rule::in(MentorProgram::TOPICS)],
            'price_30_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'price_60_cents' => ['required', 'integer', 'min:0', 'max:100000'],
            'points' => ['required', 'boolean'],
            'max_mentees' => ['required', 'integer', 'min:1', 'max:30'],
            'who' => ['required', Rule::in(MentorSetting::WHO)],
        ];
    }

    /** @return array{topics: list<string>, price_30_cents: int, price_60_cents: int, points: bool, max_mentees: int, who: string} */
    public function values(): array
    {
        /** @var list<string> $topics */
        $topics = array_values(array_filter((array) $this->input('topics', []), is_string(...)));

        return [
            'topics' => $topics, 'price_30_cents' => $this->integer('price_30_cents'), 'price_60_cents' => $this->integer('price_60_cents'),
            'points' => $this->boolean('points'), 'max_mentees' => $this->integer('max_mentees'), 'who' => $this->string('who')->toString(),
        ];
    }
}
