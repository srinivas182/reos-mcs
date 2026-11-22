<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\MessageReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Report a conversation (B6.1b): why, optional details, who (in a group), and whether to block too. */
final class ReportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(MessageReport::REASONS)],
            'details' => ['nullable', 'string', 'max:1000'],
            'user' => ['nullable', 'integer'],
            'block' => ['boolean'],
        ];
    }
}
