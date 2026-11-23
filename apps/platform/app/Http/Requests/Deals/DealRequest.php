<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use App\Models\Deal;
use App\Models\DealDate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A deal the agent tracks in REOS (B9.1): property, side, stage, price, commission, parties and key dates. */
final class DealRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string|In>> */
    public function rules(): array
    {
        return [
            'property' => ['required', 'string', 'min:3', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'side' => ['required', Rule::in(Deal::SIDES)],
            'stage' => ['required', Rule::in(Deal::STAGES)],
            'price' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'commission' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'parties' => ['present', 'array', 'max:20'],
            'parties.*.name' => ['required', 'string', 'max:120'],
            'parties.*.role' => ['required', 'string', 'max:60'],
            'parties.*.email' => ['nullable', 'email', 'max:255'],
            'parties.*.phone' => ['nullable', 'string', 'max:40'],
            'dates' => ['present', 'array', 'max:30'],
            'dates.*.kind' => ['required', Rule::in(DealDate::KINDS)],
            'dates.*.label' => ['nullable', 'string', 'max:120'],
            'dates.*.due_on' => ['required', 'date_format:Y-m-d'],
            'dates.*.done' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{property: string, city: string|null, side: string, stage: string, price: int|null, commission: string|null, parties: list<array{name: string, role: string, email: string|null, phone: string|null}>, dates: list<array{kind: string, label: string|null, due_on: string, done: bool}>}
     */
    public function values(): array
    {
        $text = static fn (mixed $v): ?string => is_string($v) && trim($v) !== '' ? trim($v) : null;
        $string = static fn (mixed $v, string $or = ''): string => is_string($v) ? $v : $or;
        $parties = [];
        foreach ((array) $this->validated('parties') as $p) {
            if (is_array($p)) {
                $parties[] = ['name' => (string) $text($p['name'] ?? null), 'role' => (string) $text($p['role'] ?? null), 'email' => $text($p['email'] ?? null), 'phone' => $text($p['phone'] ?? null)];
            }
        }
        $dates = [];
        foreach ((array) $this->validated('dates') as $d) {
            if (is_array($d)) {
                $dates[] = ['kind' => $string($d['kind'] ?? null, 'other'), 'label' => $text($d['label'] ?? null), 'due_on' => $string($d['due_on'] ?? null), 'done' => filter_var($d['done'] ?? false, FILTER_VALIDATE_BOOLEAN)];
            }
        }
        $price = $this->validated('price');
        $commission = $this->validated('commission');

        return [
            'property' => $string($this->validated('property')), 'city' => $text($this->validated('city')),
            'side' => $string($this->validated('side'), 'buy'), 'stage' => $string($this->validated('stage'), 'active'),
            'price' => is_numeric($price) ? (int) $price : null, 'commission' => is_numeric($commission) ? (string) $commission : null,
            'parties' => $parties, 'dates' => $dates,
        ];
    }
}
