<?php

declare(strict_types=1);

namespace App\Http\Requests\Connections;

use App\Connections\ConnectorRegistry;
use App\Connections\HistoryRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/** Start connecting a tool: which one, and how much history the first sync fetches. */
final class StartConnectionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|In|string>> */
    public function rules(): array
    {
        return [
            /** The tool's register id, for example `google-workspace`. */
            'integration' => ['required', 'string', Rule::in(ConnectorRegistry::ids())],
            /** How far back the first sync reaches: `3m` (default), `12m` or `all`. */
            'history' => ['sometimes', 'nullable', Rule::enum(HistoryRange::class)],
        ];
    }

    public function history(): HistoryRange
    {
        return $this->filled('history') ? HistoryRange::from($this->string('history')->toString()) : HistoryRange::DEFAULT;
    }
}
