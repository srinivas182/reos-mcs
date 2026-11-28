<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Leads\OpenHouses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Sign-ins from kiosk mode on the agent's phone or tablet, possibly made offline (B14.3a). */
final class SyncVisitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'visits' => ['required', 'array', 'min:1', 'max:200'],
            'visits.*.client_id' => ['required', 'string', 'max:64'],
            'visits.*.name' => ['required', 'string', 'min:2', 'max:120'],
            'visits.*.email' => ['nullable', 'required_without:visits.*.phone', 'string', 'email', 'max:160'],
            'visits.*.phone' => ['nullable', 'required_without:visits.*.email', 'string', 'regex:/^\+?[0-9 ().-]{7,30}$/'],
            'visits.*.has_agent' => ['sometimes', 'boolean'],
            'visits.*.pre_approved' => ['sometimes', 'boolean'],
            'visits.*.timeline' => ['nullable', 'string', Rule::in(OpenHouses::TIMELINES)],
            'visits.*.consent' => ['accepted'],
            'visits.*.signed_in_at' => ['nullable', 'date'],
        ];
    }

    /** @return list<array{client_id: string, name: string, email: string|null, phone: string|null, has_agent: bool, pre_approved: bool, timeline: string|null, consent: bool, signed_in_at: string|null}> */
    public function visits(): array
    {
        $rows = [];

        foreach ((array) $this->input('visits', []) as $v) {
            $v = is_array($v) ? $v : [];
            $text = static fn (string $k): ?string => is_string($v[$k] ?? null) && trim($v[$k]) !== '' ? trim($v[$k]) : null;
            $rows[] = [
                'client_id' => (string) $text('client_id'), 'name' => (string) $text('name'), 'email' => $text('email'), 'phone' => $text('phone'),
                'has_agent' => filter_var($v['has_agent'] ?? false, FILTER_VALIDATE_BOOLEAN), 'pre_approved' => filter_var($v['pre_approved'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'timeline' => $text('timeline'), 'consent' => true, 'signed_in_at' => $text('signed_in_at'),
            ];
        }

        return $rows;
    }
}
