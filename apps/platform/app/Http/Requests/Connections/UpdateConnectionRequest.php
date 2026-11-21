<?php

declare(strict_types=1);

namespace App\Http\Requests\Connections;

use App\Connections\HistoryRange;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/** "What to sync" (Connections and Sync journey, screen 7). */
final class UpdateConnectionRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|Enum|string>> */
    public function rules(): array
    {
        return [
            /** How far back to sync: `3m`, `12m` or `all`. */
            'history' => ['required', Rule::enum(HistoryRange::class)],
        ];
    }

    public function history(): HistoryRange
    {
        return HistoryRange::from($this->string('history')->toString());
    }
}
