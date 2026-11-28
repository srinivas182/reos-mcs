<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use Illuminate\Foundation\Http\FormRequest;

/** The team agreement (A17.3): the brokerage's and the team's percent; the agent gets the rest. */
final class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return ['brokerage_percent' => ['required', 'integer', 'between:0,100'], 'team_percent' => ['required', 'integer', 'between:0,100']];
    }
}
