<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Leads\OpenHouses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A visitor's sign-in (B14.3a): a name and a way to reach them, their
 * agent and loan status, and their consent to be contacted. `website` is
 * a hidden field people never fill in.
 */
final class VisitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:160'],
            'phone' => ['nullable', 'required_without:email', 'string', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'has_agent' => ['sometimes', 'boolean'],
            'pre_approved' => ['sometimes', 'boolean'],
            'timeline' => ['nullable', 'string', Rule::in(OpenHouses::TIMELINES)],
            'consent' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    /** @return array{name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: string|null, consent: bool} */
    public function visitor(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->filled('email') ? $this->string('email')->toString() : null,
            'phone' => $this->filled('phone') ? $this->string('phone')->toString() : null,
            'has_agent' => $this->boolean('has_agent'),
            'pre_approved' => $this->boolean('pre_approved'),
            'timeline' => $this->filled('timeline') ? $this->string('timeline')->toString() : null,
            'consent' => true,
        ];
    }
}
