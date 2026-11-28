<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use Illuminate\Foundation\Http\FormRequest;

/** A brokerage rule (A15.2): its topic and words. */
final class BrokerageRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'topic' => ['required', 'string', 'min:2', 'max:120'],
            'text' => ['required', 'string', 'min:5', 'max:4000'],
        ];
    }
}
