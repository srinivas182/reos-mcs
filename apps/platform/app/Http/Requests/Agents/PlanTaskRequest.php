<?php

declare(strict_types=1);

namespace App\Http\Requests\Agents;

use App\Agents\AgentPlans;
use Illuminate\Foundation\Http\FormRequest;

/** A plan task ticked or unticked, for its week (A11.1b). */
final class PlanTaskRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['week' => ['required', 'integer', 'min:1', 'max:'.AgentPlans::WEEKS], 'done' => ['required', 'boolean']];
    }
}
