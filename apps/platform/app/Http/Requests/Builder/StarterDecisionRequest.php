<?php

declare(strict_types=1);

namespace App\Http\Requests\Builder;

use App\Content\Review\StarterReview;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** Approve, retire or restore starter bank questions (screen 45, A9.1): one, or every one shown. */
final class StarterDecisionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|In>> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(StarterReview::DECISIONS)],
            'ids' => ['required', 'array', 'min:1', 'max:300'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function decision(): string
    {
        $decision = $this->validated('decision');

        return is_string($decision) ? $decision : '';
    }

    /** @return list<int> */
    public function ids(): array
    {
        $ids = $this->validated('ids');

        return array_values(array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, is_array($ids) ? $ids : []));
    }
}
