<?php

declare(strict_types=1);

namespace App\Http\Requests\Listings;

use App\Mls\Cmas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/** A CMA's radius (miles) and date range (months) (B10.2b). */
final class CmaRequest extends FormRequest
{
    /** @return array<string, list<string|In>> */
    public function rules(): array
    {
        return [
            'radius' => ['nullable', 'numeric', Rule::in(Cmas::RADII)],
            'months' => ['nullable', 'integer', Rule::in(Cmas::MONTHS)],
        ];
    }

    public function radius(): float
    {
        return $this->filled('radius') ? $this->float('radius') : 1.0;
    }

    public function months(): int
    {
        return $this->filled('months') ? $this->integer('months') : 6;
    }
}
